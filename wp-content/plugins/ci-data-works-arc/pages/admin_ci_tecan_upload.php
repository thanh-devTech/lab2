<?php

if($_POST['act'] =='upload_file' && !empty($_FILES))
{
    require TRCPDD_PATH. 'inc/PHPExcel/IOFactory.php';


    $error = "";
    $accepted = array("csv","xls","xlsx",'txt');
    $uploaded = array();

    function tr_pdd_get_batch_id_auto($core_bid,$batch_num)
    {
        global $wpdb;
        if(strpos($core_bid,'-')>0)
        {
            list($core_bid,$n2) = explode('-', $core_bid);
        }
        //if($batch_num<10)$batch_num='0'.$batch_num;
        $bid = $core_bid.'-'.$batch_num;

        //check exists
        $number_auto = $wpdb->get_var("select bn_number_auto from ci_batches_notes WHERE bn_core_bid='{$core_bid}'  AND bn_number = '{$batch_num}' ");
        if($number_auto>0)
        {
            $bid = $core_bid.'-'.$number_auto;
        }else{
            $number_auto = $wpdb->get_var("select max(bn_number_auto) from ci_batches_notes WHERE bn_core_bid='{$core_bid}' ") + 1;
            $bid = $core_bid.'-'.$number_auto;
            $data = array(
                'bn_bid'=>$bid,
                'bn_created'=>Tr_Base_Class_V4::date('Y-m-d H:i:s'),
                'bn_user' => get_current_user_id(),
                'bn_number' => $batch_num,
                'bn_core_bid' => $core_bid,
                'bn_number_auto' => $number_auto
                );
            $wpdb->insert('ci_batches_notes',$data);
        }
        return $bid;
    }
    ?>
    <div class="wrap-log">
        <img id="ajax-loader" src="<?php echo TRCPDD_URL?>images/ajax-loader.gif" />
        <p><em>Processing...</em></p>
        <ul class="report-log">
            <?php
            if(!empty($_FILES)) {
                tr_pdd_activity("Gathering data...");
                // array-ify uploads
                if(!is_array($_FILES['files']['tmp_name'])) {
                    $files[] = $_FILES['files'];
                } else {
                    $count = 0;
                    foreach($_FILES['files']['tmp_name'] as $t) {
                        foreach($_FILES['files'] as $key => $f) {
                            $new_file[$key] = $f[$count];
                        }
                        $files[] = $new_file;
                        $count++;
                    }
                }

                tr_pdd_activity("Verifying format...");
                $upload_dir = tr_wp_upload_dir();
                $doc_dir = $upload_dir['basedir'].'/tecan';

                $upl_error = false;
                // verify files
                foreach($files as $f)
                {
                    if(is_uploaded_file($f['tmp_name']))
                    {
                        if((in_array(substr($f['name'], -3),$accepted) || in_array(substr($f['name'], -4),$accepted)) && strpos($f['name'], ".php") === false) {
                            $filename = $f['name'];
                            $path = $doc_dir.'/'. $filename;
                            if(move_uploaded_file($f['tmp_name'], $path)) {
                                $uploaded[] = $path;
                                tr_pdd_activity("File uploaded successfully: <em>". $f['name'] ."</em>");
                            } else $upl_err = true;
                        } else $upl_err = true;

                        if($upl_err) {
                            $error = 1;
                            $not_uploaded[] = $f;
                            tr_pdd_activity("The following file was not accepted: <em>". $f['name'] ."</em>", true);
                        }
                    }
                }

                if(empty($uploaded)) {
                    tr_pdd_activity("Error", true);
                    exit;
                }


                // read data
                $fcount = 0;
                if(empty($error)) {
                    foreach($uploaded as $file) {
                        $has_txt_file = false;

                        tr_pdd_activity("Parsing file ". ++$fcount ."...");
                        $row_data = array();
                        $has_header = false;

                        if(stripos($file,'.txt')!==false)
                        {
                            $has_txt_file = true;
                            $dt = file_get_contents($file);
                            $rows = explode("\n",$dt);

                            foreach($rows as $row => $rowdt)
                            {
                                $rowdt = str_replace(array("\t\"","\"\t"),"\t",$rowdt);
                                $rowdt = explode("\t",$rowdt);
                                if($row==0 || empty($headers[$file]))
                                {
                                    $headers[$file] = array($rowdt);
                                }else{
                                    $row_data[$row] = array($rowdt);
                                }
                            }
                        }else{
                            try{
                                $filetype = PHPExcel_IOFactory::identify($file);

                                $objReader = PHPExcel_IOFactory::createReader($filetype);
                                $objPHPExcel = $objReader->load($file);

                                $sheet = $objPHPExcel->getSheet(0);
                                $highestRow = $sheet->getHighestRow();
                                $highestColumn = $sheet->getHighestColumn();
                                $has_header = false;

                                for($row = 1; $row <= $highestRow; $row++) {
                                    try{
                                        $rowdt = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
                                    }catch (Exception $e)
                                    {
                                        if($row>1)continue;
                                    }

                                    if($row == 1 || !$has_header) {
                                        $headers[$file] = $rowdt;
                                        if(!empty($headers[$file][0][0]) || !empty($headers[$file][0][1])){
                                            $has_header = true;
                                        }
                                    } else {
                                        $row_data[$row] = $rowdt;
                                    }
                                }
                            }catch (Exception $e)
                            {
                                echo '<h2>Error Please try export to csv and upload again</h2>';
                                exit;
                            }
                        }

                        $headers[$file][0] = array_map('trim',$headers[$file][0]);
                        $file_data[$file] = $row_data;

                        tr_pdd_activity("Done.");
                    }

                    $rows = array();
                    $date_cols = array("Collection Date");
                    $time_cols = array('Time');
                    $use_cols = array(
                        'Position',
                        'TubeID',
                     //   'ScanError',
                    //    'SRCRack',
                     //   'SRCPos',
                      //  'SRCTubeID',
                      //  'Volume',
                       // 'Error',
                       // 'SRCRackID',
                       // 'GridPos',
                       // 'SiteOnGrid',
                       // 'TipNumber',
                       // 'DetectVol',
                    );
                    $save_cols = array_merge($date_cols,$use_cols);
                    $save_cols = array_merge($time_cols,$save_cols);


                    foreach($file_data as $filename => $row_data)
                    {
                        $expl = explode("/", $filename);
                        $file = end($expl);
                        foreach ($row_data as $index => $row) {
                            $c = 0;
                            $row_data = array();
                            foreach ($row[0] as $r)
                            {
                                $head_key = $headers[$filename][0][$c];
                                if (in_array($head_key, $date_cols)) {

                                    if ($has_txt_file) {
                                        $r = gmdate('m/d/Y', strtotime($r));
                                    } else {
                                        $r = gmdate('m/d/Y', PHPExcel_Shared_Date::ExcelToPHP($r));
                                    }

                                }else if(in_array($head_key, $time_cols))
                                {
                                    if ($has_txt_file) {
                                    } else {
                                        $r = gmdate('H:i:s', PHPExcel_Shared_Date::ExcelToPHP($r));
                                    }
                                }
                                $row_data[$head_key] = $r;
                                $c++;
                            }
                            $row_data['filename'] = $file;

                            $rows[]= $row_data;
                        }
                    }

                    //insert to db
                    $file_batches = array();
                    $letter = array('letter'=>'','number'=>'');
                    $batch_id = '';
                    $core_bid = '';
                    $batch_num = '';
                    foreach($rows as $row)
                    {
                        $file = $row['filename'];

                        $bat = $wpdb->get_row("select mid,assen,batch_id from ci_batches where assen='{$row['TubeID']}'");

                        if(!isset($file_batches[$file])){
                            preg_match("/([0-9]{2})[\.|-]([0-9]{2})[\.|-]([0-9]{2,4})/",$file,$matches);
                            if(strlen($matches[3])==2)
                            {
                                $matches[3] = '20'.$matches[3];
                            }
                            $batch_id = $core_bid = $matches[3].$matches[1].$matches[2];

                            preg_match("/batch[\s]*([0-9]+)/",strtolower($file),$matches);
                            $batch_num = $matches[1];
                            if(empty($batch_num))
                            {
                                $batch_num = md5(strtolower($file));
                            }
                            $batch_id= tr_pdd_get_batch_id_auto($core_bid, $batch_num);

                            if($bat && !empty($bat->batch_id))
                            {
                                $core_bid = $bat->batch_id;
                                $batch_id = tr_pdd_get_batch_id_auto($bat->batch_id, $batch_num);
                            }

                            $letter = trpdd_get_tecan_letters();
                            //check
                        }

                        //get from batches tbl



                        $insert = array(
                            'te_position' => $row['Position'],
                            'te_assen'=>$row['TubeID'],
                            'te_time' => $row['Time'],
                            'te_filename'=>$row['filename'],
                            'te_batch_id' => $batch_id,
                            'te_letter' => $letter['letter'],
                            'te_number' => $letter['number'],
                            'te_created' => Tr_Base_Class_V4::date('Y-m-d H:i:s')
                        );

                        $rs = $wpdb->insert('ci_tecan',$insert);
                        if($rs===false)
                        {
                            $te_ID = $wpdb->get_var("select te_ID from ci_tecan where te_assen='{$insert['te_assen']}'");
                            unset($insert['te_created']);
                            unset($insert['te_letter']);
                            unset($insert['te_number']);
                            $wpdb->update('ci_tecan',$insert,array('te_ID'=>$te_ID));
                        }else{
                            $te_ID = $wpdb->insert_id;
                        }


                        if(!$te_ID)continue;



                        //update batch
                        if($bat && $bat->mid>0)
                        {
                            $update_batch = array(
                                'position'=>$insert['te_position'],
                                'batch_status' => 'cold_storage',
                            );
                            if(empty($bat->batch_id))
                            {
                                $update_batch['batch_id'] = $batch_id;
                            }
                            else
                            {
                                list($old_batch_id,$batn)= explode('-',$bat->batch_id);
                                $update_batch['batch_id'] = tr_pdd_get_batch_id_auto($old_batch_id, $batch_num);
                            }
                            $wpdb->update('ci_batches',$update_batch,array('mid'=> $bat->mid));
                        }

                    }


                    foreach($uploaded as $file) {
                        @copy($file,WP_CONTENT_DIR.'/log/tecan/'.basename($file));
                        unlink($file);
                    }



                    echo "</ul>";
                }else{
                    echo $error;exit;
                }
            }

            ?>
    </div>

    <meta http-equiv="refresh" content="1; url=admin.php?page=ci_prep_station&tab=batches">
    <?php
    return;
}
?>

<div class="form-wrap center">
    <form enctype="multipart/form-data" method="post">
        <input type="hidden" name="act" value="upload_file">

        <h3>Upload Tecan Data</h3>
        <input type="file" id="upload-files" name="files[]"
               accept="text/csv,text/txt, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
               multiple/>
        <input type="submit" value="Upload" class="button-primary"/>

    </form>
</div>
