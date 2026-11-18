<?php
include(TRCPDD_PATH . 'inc/functions_rack_plate.php');

$files = $_SESSION['files_study_import'];

$match_header = array(
    'file' => 'file',
    'cell_count' => 'cell_count',
);

$plateIDs       = array();
$plate_datas    = array();
$runtime_file = '';
foreach ($files as $filename => $meta) {
    //if ($meta['done']) continue;

    Tr_Session_Class::add("Importing FA File: " . $filename);

    $file_id    = $meta['file_id'];
    $filepath   = $log_dir . $filename;

    $data       = trpdd_get_data($filepath, '', $match_header, true);

    if (count($data['data']) == 0) {
        echo 'No data';
        exit;
    }

    foreach ($data['data'] as $row) {
        $file       = $row['file'];
        $cell_count = $row['cell_count'];

        $arr1 = explode('-', $file, 3);
       
        $plateID    = $arr1[0];
        $arr2       = $arr1[1];

        if (strlen($arr1[1]) == 2 && isset($arr1[2])) {
            $plateID .= '-' . $arr1[1];
            $arr2 = $arr1[2];
        }
        else if (strlen($arr1[1]) ==3 && substr($arr1[1],0,1)=='T' && isset($arr1[2])) {
            $plateID .= '-' . $arr1[1];
            $arr2 = $arr1[2];
        } else if (isset($arr1[2])) {
            $arr2 .= '-' . $arr1[2];
        }


        list($runtime, $image_number) = explode('_', $arr2, 2);

        $runtime_file   = $runtime;
        $runtime        = substr($runtime, 0, 1);
        $image_number   = str_ireplace('s', '', $image_number);
        $image_number   = intval($image_number);

        $plateIDs[$plateID] = $plateID;
        if (!isset($plate_datas[$plateID])) {
            $plate_datas[$plateID] = array();
        }
        $plate_datas[$plateID][$runtime . '-' . $image_number] = $cell_count;
        $plate_datas[$plateID]['file_id'] = $file_id;
    }
}



//calculator

$plateIDs_str = implode("','", $plateIDs);
$plates = $wpdb->get_results("select * 
	from ci_plates pl
	left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
	where ra.re_subtitle in ('{$plateIDs_str}')", ARRAY_A);

if (empty($plates)) {
 
    Tr_Session_Class::add_error("Not found Plate");
}

$reports = array();
foreach ($plates as $plate) {
    $platedata  = $plate_datas[$plate['re_subtitle']];
    $rack_id    = $plate['pl_rack_ID'];

    $file_id    = $platedata['file_id'];
    $meta_data  = $wpdb->get_var("select rf_meta_data from ci_reports_files where rf_ID = {$file_id}");
    $meta_data  = json_decode($meta_data, true);

    $listassens     = trpdd_get_assens_plate($plate);


    $cell_cal   = array();

    foreach (array(1, 25, 49) as $c_pos) {


        if (!isset($listassens[$c_pos])) {
            continue;
        }
        $added_assens = array();
        $forassens = array();


        $has_value_pl = false;
        //  var_dump($platedata);
        $runtime1 = '';
        for ($ii = 3; $ii <= 10; $ii++) {

            $n_pos  = $c_pos + ($ii - 3) * 3;

            $assen  = $listassens[$n_pos]['assen'];
            $com    = $listassens[$n_pos]['combine_position'];

            //if(empty($com))continue;
            $runtime1 = $com;


            $plkey = $com . '-' . $listassens[$n_pos]['s_num'];
            /*
            echo '<br><br>';
            echo '$c_pos: ' . $c_pos . '<br>';
            echo '$n_pos: ' . $n_pos . '<br>';
            echo $assen;
            echo '<br>';
            echo ($plkey) . '<br>';
            */

            if (isset($platedata[$plkey])) {
                $has_value_pl = true;

                if (!isset($added_assens[$assen])) {
                    $forassens[] = $listassens[$n_pos];
                    $added_assens[$assen] = $assen;
                }
            } else {
                //$has_value_pl = true;
                //$com = 2;
            }

            $cell_cal['h' . $ii] = $platedata[$com . '-' . $listassens[$n_pos]['s_num']]; //c1-1
            $cell_cal['i' . $ii] = $platedata[$com . '-' . $listassens[$n_pos + 1]['s_num']]; //c1-2
            $cell_cal['j' . $ii] = $platedata[$com . '-' . $listassens[$n_pos + 2]['s_num']]; //c1-3

            $cell_cal['k' . $ii] = $cell_cal['h' . $ii] / 0.21;
            $cell_cal['l' . $ii] = $cell_cal['i' . $ii] / 0.21;
            $cell_cal['m' . $ii] = $cell_cal['j' . $ii] / 0.21;

            $cell_cal['n' . $ii] = $cell_cal['u' . $ii] = ($cell_cal['k' . $ii] + $cell_cal['l' . $ii] + $cell_cal['m' . $ii]) / 3;
        }

        if (!$has_value_pl) {
            $assarr = array();
            foreach ($forassens as $as) {
                $assarr[] = $as['assen'];
            }
            Tr_Session_Class::add_error("Sample in wrong run #{$runtime1}: " . implode(', ', $assarr) . ' ' . $runtime_file);
            continue;
        } else if (empty($forassens)) {
            Tr_Session_Class::add_error("Not found Accession Number");
            //continue;
        }


        $cell_cal['ah3'] = $cell_cal['s3'] = trpdd_stats_standard_deviation(array(
            $cell_cal['n3'],
            $cell_cal['n4'],
            $cell_cal['n5'],
            $cell_cal['n6'],
        ));
        $cell_cal['ai3'] = $cell_cal['s4'] = trpdd_stats_standard_deviation(array(
            $cell_cal['n7'],
            $cell_cal['n8'],
            $cell_cal['n9'],
            $cell_cal['n10'],
        ));

        $cell_cal['af3'] = $cell_cal['r3'] = ($cell_cal['n3'] + $cell_cal['n4'] + $cell_cal['n5'] + $cell_cal['n6']) / 4;
        $cell_cal['ag3'] = $cell_cal['r4'] = ($cell_cal['n7'] + $cell_cal['n8'] + $cell_cal['n9'] + $cell_cal['n10']) / 4;


        $cell_cal['aj3'] = $cell_cal['ah3'] / $cell_cal['af3'] * 100;
        $cell_cal['ak3'] = $cell_cal['ai3'] / $cell_cal['ag3'] * 100;


        $cell_cal['v3'] = $cell_cal['ah3'] > 20 && $cell_cal['aj3'] > 30 ? $cell_cal['r3'] - $cell_cal['s3'] : 0;
        $cell_cal['v4'] = $cell_cal['ai3'] > 20 && $cell_cal['ak3'] > 50 ? $cell_cal['r4'] - $cell_cal['s4'] : 0;

        $cell_cal['w3'] = $cell_cal['ah3'] > 20 && $cell_cal['aj3'] > 30 ? $cell_cal['r3'] + $cell_cal['s3'] : 10000;
        $cell_cal['w4'] = $cell_cal['ai3'] > 20 && $cell_cal['ak3'] > 50 ? $cell_cal['r4'] + $cell_cal['s4'] : 10000;

        for ($ii = 3; $ii <= 6; $ii++) {
            $cell_cal['x' . $ii] = $cell_cal['u' . $ii] >= $cell_cal['v3'] && $cell_cal['u' . $ii] <= $cell_cal['w3'] ? $cell_cal['u' . $ii] : 'NA';
        }
        for ($ii = 7; $ii <= 10; $ii++) {
            $cell_cal['x' . $ii] = $cell_cal['u' . $ii] >= $cell_cal['v4'] && $cell_cal['u' . $ii] <= $cell_cal['w4'] ? $cell_cal['u' . $ii] : 'NA';
        }
        $cell_cal['an3'] = $cell_cal['ac3'] = trpdd_stats_standard_deviation(array(
            $cell_cal['x3'],
            $cell_cal['x4'],
            $cell_cal['x5'],
            $cell_cal['x6'],
        ));
        $cell_cal['ao3'] = $cell_cal['ac4'] = trpdd_stats_standard_deviation(array(
            $cell_cal['x7'],
            $cell_cal['x8'],
            $cell_cal['x9'],
            $cell_cal['x10'],
        ));;

        $cell_cal['al3'] = $cell_cal['ab3'] = trpdd_cal_avegare(array(
            $cell_cal['x3'],
            $cell_cal['x4'],
            $cell_cal['x5'],
            $cell_cal['x6'],
        ));
        $cell_cal['am3'] = $cell_cal['ab4'] = trpdd_cal_avegare(array($cell_cal['x7'], $cell_cal['x8'], $cell_cal['x9'], $cell_cal['x10']));

        $cell_cal['ap3'] = $cell_cal['an3'] / $cell_cal['al3'] * 100;
        $cell_cal['aq3'] = $cell_cal['ao3'] / $cell_cal['am3'] * 100;

        $cell_cal['av3'] = $cell_cal['ab3'];
        $cell_cal['av4'] = $cell_cal['ab4'];


        /*
        Formula for VCAM-1 (excel AW3, AW5 and AW7): 
        =IF(AND(AN3>50,AP3>30),"Re-Run, high variability",IF(AV3>1352,"Re-Run, out of range High VCAM",IF(AV3<38,"Re-Run, out of range Low VCAM","Pass")))
        Formula for P-selectin (excel AW4, AW6 and AW8):
        =IF(AND(AO3>20,AQ3>50),"Re-Run, high variability",IF(AV4>495,"Re-Run, out of range High P-selectin","Pass")
        */

        $has_rerun      = false;
        $results_arr    = array();


        $results_arr['VCAM1'] = array();
        $results_arr['VCAM1']['conc'] = $cell_cal['ab3'];


        //check rerun
        /*
        Flow Adhesion rerun acceptance criteria:
        1st run low VCAM -> 2nd run low VCAM => Report 2nd run
        1st run high VCAM -> 2nd run high VCAM => Report 2nd run
        1st run high PSEL -> 2nd run high PSEL => Report 2nd run
        1st run low VCAM -> 2nd run NOT low VCAM => Reject
        1st run high VCAM -> 2nd run NOT high VCAM => Reject
        1st run high PSEL -> 2nd run NOT high PSEL => Reject
        1st run high variability -> 2nd run NOT high variability =>  Report 2nd run
        1st run high variability -> 2nd run high variability =>  Reject
        */

        if ($cell_cal['an3'] > 50 && $cell_cal['ap3'] > 30) {
            $results_arr['VCAM1']['comments'] = 'Re-Run, high variability';
            $has_rerun = true;
        } else if ($cell_cal['av3'] > 1352) {
            $results_arr['VCAM1']['comments'] = 'Re-Run, out of range High VCAM';
            $has_rerun = true;
        } else if ($cell_cal['av3'] < 38) {
            $results_arr['VCAM1']['comments'] = 'Re-Run, out of range Low VCAM';
            $has_rerun = true;
        }

        $results_arr['PSEL'] = array();
        $results_arr['PSEL']['conc'] = $cell_cal['ab4'];

        if ($cell_cal['ao3'] > 20 && $cell_cal['aq3'] > 50) {
            $results_arr['PSEL']['comments'] = 'Re-Run, high variability';
            $has_rerun = true;
        } else if ($cell_cal['av4'] > 495) {
            $results_arr['PSEL']['comments'] = 'Re-Run, out of range High P-selectin';
            $has_rerun = true;
        }

        $data_assen     = array(
            'an3' => $cell_cal['an3'],
            'ap3' => $cell_cal['ap3'],
            'av3' => $cell_cal['av3'],
            'ao3' => $cell_cal['ao3'],
            'aq3' => $cell_cal['aq3'],
            'av4' => $cell_cal['av4'],
        );
        $rerun_status = $has_rerun ? -2 : 0;


        foreach ($forassens as $arr_assen) {

            $assen      = $arr_assen['assen'];



            $table_report = tr_pdd_get_table_report_by_btype($arr_assen['btype']);
            $table_result = tr_pdd_get_table_result_by_btype($arr_assen['btype']);

            $rackdata   = $wpdb->get_var("select other_data 
            from ci_rack_batches where re_ID = {$rack_id}
            and mid = {$arr_assen['mid']} 
            and iparent = {$arr_assen['iparent']}
            and subtype = '{$arr_assen['subtype']}'");
            $rackdata   = json_decode($rackdata, true);
            $arr_assen['test_id'] = $rackdata['vp_type'];



            $obj_report = null;
            $report_id  = 0;
            if (!isset($reports[$assen])) {
                $obj_report = $wpdb->get_row("select * from {$table_report} where patient_id = '{$assen}'");
                if ($obj_report) {
                    $report_id = $obj_report->id;

                    $reports[$assen] = $obj_report;

                    $upload_files = explode(',', $obj_report->upload_files);
                    if (!in_array($filename, $upload_files)) {
                        $upload_files[] = $filename;
                        $update_data['upload_files'] = implode(',', $upload_files);
                    }

                    if (count($update_data) > 0) {
                        $wpdb->update($table_report, $update_data, array('id' => $report_id));
                    }
                } else {
                    $report_data = array(
                        'patient_id' => $assen,
                        'batch_id' => 0,
                        'created' => date_i18n('Y-m-d H:i:s'),
                        'created_date' => date_i18n('Y-m-d'),
                        'lab_id' => CI_Base_Lab::get_current_lab_id(),
                        'covid_pos' => $rerun_status,
                    );

                    $wpdb->insert($table_report, $report_data);
                    $report_id = $wpdb->insert_id;
                }
            } else {
                $obj_report = $reports[$assen];
                $report_id = $obj_report->id;
            }

            if ($report_id > 0) {

                $rldrugs    = array();
                $testnames = array();
                $testid     = isset($arr_assen['test_id']) && $arr_assen['test_id'] > 0 ? $arr_assen['test_id'] : 0;
                if ($testid == 0) {
                    Tr_Session_Class::add_error('no test id');
                }
                if ($arr_assen['btype'] == 'study') {

                    $testdata   = $wpdb->get_row("select * from ci_study_tests where ID = {$testid} ");
                    $reported_values = json_decode($testdata->reported_values, true);


                    foreach ($reported_values as $arr2) {
                        $drug  = strtolower($arr2['v']);

                        if (!isset($rldrugs['VCAM1']) && strpos($drug, 'vcam') !== false) {
                            $rldrugs['VCAM1'] = $drug;
                            $testnames[$drug] = $arr2['v'];
                            break;
                        } else if (!isset($rldrugs['PSEL']) && (strpos($drug, 'psel') !== false || strpos($drug, 'p-sel') !== false)) {
                            $rldrugs['PSEL'] = $drug;
                            $testnames[$drug] = $arr2['v'];
                            break;
                        }
                    }

                    if (!isset($rldrugs['PSEL'])) {
                        $rldrugs['PSEL'] = strtolower(str_ireplace('VCAM-1', 'P-Selectin', $rldrugs['VCAM1']));
                        $testnames[$rldrugs['PSEL']] = strtoupper($rldrugs['PSEL']);
                    } else if (!isset($rldrugs['VCAM1'])) {
                        $rldrugs['VCAM1'] = strtolower(str_ireplace('P-Selectin', 'VCAM-1', $rldrugs['VCAM1']));
                        $testnames[$rldrugs['VCAM1']] = strtoupper($rldrugs['VCAM1']);
                    }
                } else {
                    if ($arr_assen['subtype'] == 'V') {
                        $rldrugs['VCAM1'] = 'VCAM1';
                    } else {
                        $rldrugs['PSEL'] = 'PSEL';
                    }
                }



                foreach ($results_arr as $drug => $arrvl) {


                    $vl = round($arrvl['conc'], 2);

                    Tr_Session_Class::add($drug . '=' . $vl);

                    $drugid = isset($rldrugs[$drug]) ? $rldrugs[$drug] : '';
                    if (empty($drugid)) continue;

                    Tr_Session_Class::add("Imported: {$assen} : " . (isset($testnames[$drugid]) ? $testnames[$drugid]  : $drug) . '=' . $vl);


                    $rs_id = $wpdb->get_var("select id from {$table_result} 
                        where report_id = {$report_id} and drug = '{$drugid}'");

                    $rsdata = array(
                        'report_id' => $report_id,
                        'drug' => $drugid,
                        'conc' => $vl,
                        'comments' => $arrvl['comments']
                    );
                    if ($testid > 0) {
                        $rsdata['group_id'] = $testid;
                    }

                    if ($rs_id > 0) {
                        $wpdb->update($table_result, $rsdata, array('id' => $rs_id));
                    } else {
                        $wpdb->insert($table_result, $rsdata);
                    }
                }



                $meta_data[$assen] = $data_assen;
                $meta_data = json_encode($meta_data);
            } else {
                Tr_Session_Class::add_error("no report");
            }
        }
    }

    $wpdb->update('ci_reports_files', array('rf_meta_data' => $meta_data), array('rf_ID' => $file_id));
}
