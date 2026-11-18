<?php
include(TRCPDD_PATH . 'inc/functions_rack_plate.php');

$files = $_SESSION['files_blood_import'];

$match_header = array(
    'file' => 'file',
    'cell_count' => 'cell_count',
);

$plateIDs       = array();
$plate_datas    = array();
$runtime_file = '';
foreach ($files as $filename => $meta) {
    if ($meta['done']) continue;

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
        } else if (isset($arr1[2])) {
            $arr2 .= '-' . $arr1[2];
        }


        list($runtime, $image_number) = explode('_', $arr2, 2);

        $runtime_file = $runtime;
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

    //var_dump($plates);exit;
foreach ($plates as $plate) {
    $platedata  = $plate_datas[$plate['re_subtitle']];

    $file_id    = $platedata['file_id'];

    $assens     = trpdd_get_assens_plate($plate);
    
    $cell_cal   = array();

    foreach (array(1, 25, 49) as $c_pos) {
        if (!isset($assens[$c_pos])) {
            continue;
        }
        $assen = $assens[$c_pos]['assen'];
        
        $has_value_pl = false;
        for ($ii = 3; $ii <= 10; $ii++) {
            $n_pos = $c_pos + ($ii - 3) * 3;

            $com = $assens[$n_pos]['combine_position'];

            if (isset($platedata[$com . '-' . $assens[$n_pos]['s_num']])) {
                $has_value_pl = true;
            }else{
                //$has_value_pl = true;
                //$com = 2;
            }

            $cell_cal['h' . $ii] = $platedata[$com . '-' . $assens[$n_pos]['s_num']]; //c1-1
            $cell_cal['i' . $ii] = $platedata[$com . '-' . $assens[$n_pos + 1]['s_num']]; //c1-2
            $cell_cal['j' . $ii] = $platedata[$com . '-' . $assens[$n_pos + 2]['s_num']]; //c1-3

            $cell_cal['k' . $ii] = $cell_cal['h' . $ii] / 0.21;
            $cell_cal['l' . $ii] = $cell_cal['i' . $ii] / 0.21;
            $cell_cal['m' . $ii] = $cell_cal['j' . $ii] / 0.21;

            $cell_cal['n' . $ii] = $cell_cal['u' . $ii] = ($cell_cal['k' . $ii] + $cell_cal['l' . $ii] + $cell_cal['m' . $ii]) / 3;
        }

        if (!$has_value_pl) {
            Tr_Session_Class::add_error("Sample in wrong run #{$com}: ".$assen. ' '.$runtime_file);
            continue;
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

        $assen_data     = array(
            'an3' => $cell_cal['an3'],
            'ap3' => $cell_cal['ap3'],
            'av3' => $cell_cal['av3'],
            'ao3' => $cell_cal['ao3'],
            'aq3' => $cell_cal['aq3'],
            'av4' => $cell_cal['av4'],
        );
        $rerun_status = $has_rerun ? -2 : 0;

        $report = $wpdb->get_row("select * from ci_reports_blood where patient_id = '{$assen}'");
        //var_dump($report);exit;
        $report_id = $report && $report->id > 0 ? $report->id : 0;
        if (!$report_id) {
            $report_data = array(
                'patient_id' => $assen,
                'batch_id' => 0,
                'created' => date_i18n('Y-m-d H:i:s'),
                'created_date' => date_i18n('Y-m-d'),
                'lab_id' => CI_Base_Lab::get_current_lab_id(),
                'covid_pos' => $rerun_status,
            );

            $wpdb->insert('ci_reports_blood', $report_data);
            $report_id = $wpdb->insert_id;
        } else {

            $oldrs = $wpdb->get_results("select drug, conc,comments 
            from ci_results_blood 
            where report_id = {$report_id}
            and drug in ('VCAM1','PSEL')", ARRAY_A);
            $old_value = array();
            foreach ($oldrs as $oldr) {
                $old_value[$oldr->drug] = $oldr;
            }
            $rejected = '';
            if ($has_rerun && count($old_value) > 0) {
                //B
                if (
                    $old_value['VCAM1']['conc'] > $results_arr['VCAM1']['conc'] + ($results_arr['VCAM1']['conc'] * 15 / 100)
                    && $old_value['PSEL']['conc'] > $results_arr['PSEL']['conc'] + ($results_arr['PSEL']['conc'] * 15 / 100)
                ) {
                    $rejected = 'Rejected - Indeterminate result, suggest recollection';
                }
            }

            if (!empty($rejected)) {
                //find rejected code
                $rejected_code = $wpdb->get_var("select s_key from ci_batches_status 
                where s_text = '{$rejected}' and btype='blood' ");
                if (empty($rejected_code)) {
                    $rejected_code = 're_rirsr';
                    $wpdb->insert('ci_batches_status', array('s_key' => $rejected_code, 's_text' => $rejected, 'btype' => 'blood'));
                } else {
                    $rejected_code = 're_' . $rejected_code;
                }
                $wpdb->update('ci_batches_blood', array('batch_status' => $rejected_code), array('assen' => $assen));
            }

            $update_data = array();

            $update_data['covid_pos'] = $rerun_status;

            $upload_files = explode(',', $report->upload_files);
            if (!in_array($filename, $upload_files)) {
                $upload_files[] = $filename;
                $update_data['upload_files'] = implode(',', $upload_files);
            }

            if (count($update_data) > 0) {
                $wpdb->update('ci_reports_blood', $update_data, array('id' => $report_id));
            }
        }

        if ($report_id > 0) {

            Tr_Session_Class::add("Imported: {$assen}");

            foreach ($results_arr as $drug => $arrvl) {
                $vl = round($arrvl['conc'], 2);
                $rs_id = $wpdb->get_var("select id from ci_results_blood 
					where report_id = {$report_id} and drug = '{$drug}'");
                $rsdata = array(
                    'report_id' => $report_id,
                    'drug' => $drug,
                    'conc' => $vl,
                    'comments' => $arrvl['comments']
                );
                if ($rs_id > 0) {
                    $wpdb->update('ci_results_blood', $rsdata, array('id' => $rs_id));
                } else {
                    $wpdb->insert('ci_results_blood', $rsdata);
                }
            }

            $meta_data = $wpdb->get_var("select rf_meta_data from ci_reports_files where rf_ID = {$file_id}");
            $meta_data = json_decode($meta_data, true);
            $meta_data[$assen] = $assen_data;
            $meta_data = json_encode($meta_data);

            $wpdb->update('ci_reports_files', array('rf_meta_data' => $meta_data), array('rf_ID' => $file_id));
        }
    }
}
