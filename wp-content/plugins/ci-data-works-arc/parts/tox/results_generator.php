<?php
global $wpdb, $ci_found_columns;

$created_date   = date_i18n('Y-m-d');
$created        = date_i18n("Y-m-d H:i:s");
$btype          = 'tox';
$type_import    = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
$act            = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$user_id        = get_current_user_id();

$all_instruments = $wpdb->get_col("select instrument from `ci_instruments` where btype='tox' ");
$all_instruments = array_map("strtoupper", $all_instruments);
usort($all_instruments, function ($a, $b) {
    return strlen($b) - strlen($a);
});

if (isset($_GET['debug_ms'])) {
    var_dump($all_instruments);
}

if ($act == 'ci_doctor_process' && (!empty($_FILES) || count($_POST['files']) > 0 || isset($_GET['autoimportqc']))) {
    require TRCPDD_PATH . 'inc/PHPExcel/IOFactory.php';
    $wpdb->suppress_errors(true);

    $wpdb->query("drop table ci_excel_{$wpdb->prefix}");
    $sql = "CREATE TABLE `ci_excel_{$wpdb->prefix}` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `filename` text,
    `row` int(11) DEFAULT NULL,
    `col` varchar(256) DEFAULT NULL,
    `val` text,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

    $wpdb->query($sql);

    $error = "";
    $accepted = array("csv", "xls", "xlsx", 'txt');
    $uploaded = array();
?>
    <div class="wrap-log">
        <img id="ajax-loader" src="<?php echo TRCPDD_URL ?>images/ajax-loader.gif" />
        <p><em>Processing...</em></p>
        <ul class="report-log">
            <?php

            $upl_error = false;


            if (!empty($_FILES)) {
                tr_pdd_activity("Gathering data...");
                // array-ify uploads
                if (!is_array($_FILES['files']['tmp_name'])) {
                    $files[] = $_FILES['files'];
                } else {
                    $count = 0;
                    foreach ($_FILES['files']['tmp_name'] as $t) {
                        $new_file = array();
                        foreach ($_FILES['files'] as $key => $f) {
                            $new_file[$key] = $f[$count];
                        }
                        $files[] = $new_file;
                        $count++;
                    }
                }

                tr_pdd_activity("Verifying format...");

                // verify files
                foreach ($files as $f) {

                    if (is_uploaded_file($f['tmp_name'])) {
                        $upl_err  = false;
                        if ((in_array(substr($f['name'], -3), $accepted) || in_array(substr($f['name'], -4), $accepted)) && strpos($f['name'], ".php") === false) {
                            $filename = $f['name'];
                            $filepath = $log_dir . '/' . $filename;

                            //change old file name if exist
                            $num = 1;
                            $oldfilename = $filename;
                            while (file_exists($filepath)) {
                                $filename = str_replace('.' . $ext, '_' . $num . '.' . $ext, $oldfilename);
                                $filepath = $log_dir . '/' . $filename;
                                $num++;
                            }


                            if (move_uploaded_file($f['tmp_name'], $filepath)) {
                                $uploaded[] = $filepath;
                                tr_pdd_activity("File uploaded successfully: <em>" . $f['name'] . "</em>");
                            } else $upl_err = true;
                        } else $upl_err = true;

                        if ($upl_err) {
                            $error = 1;
                            $not_uploaded[] = $f;
                            tr_pdd_activity("The following file was not accepted: <em>" . $f['name'] . "</em>", true);
                        }
                    } else {
                        //var_dump($f);exit;
                    }
                }
            } else if (count($_POST['files']) > 0) {

                foreach ($_POST['files'] as $filename) {
                    $filepath = $log_dir . '/' . $filename;

                    tr_pdd_activity("File uploaded successfully: <em>" . $filename . "</em>");
                }
                $_SESSION['importqcfiles'] = $_POST['files'];
            } else if (isset($_GET['autoimportqc'])) {
                if (isset($_GET['start'])) {
                    $wpdb->query("drop table if exists ci_autoimportqc");

                    $sql = "CREATE TABLE `ci_autoimportqc` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `filename` varchar(200),
                         `ctype` varchar(10) default 'qc',
                         `total_rows` int(11),
                         `per_run` int(11),
                         `imported` int(11),
                         `start_time` datetime,
                         `end_time` datetime,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

                    $rs = $wpdb->query($sql);


                    foreach ($_SESSION['importqcfiles'] as $f) {
                        $d = array(
                            'filename' => $f
                        );
                        $wpdb->insert('ci_autoimportqc', $d);
                    }
                    tr_pdd_activity("Total: <em>" . (count($files) - 2) . "</em>");
                }
                $files = $wpdb->get_results("select * from ci_autoimportqc limit 1");

                foreach ($files as $f) {
                    $filename = $f->filename;
                    $filepath = $log_dir . '/' . $filename;
                    $uploaded[] = $filepath;
                    tr_pdd_activity("File uploaded successfully: <em>" . $filename . "</em>");

                    $wpdb->delete('ci_autoimportqc', array('id' => $f->id));
                }
            }



            if (empty($uploaded)) {
                $wpdb->query("drop table if exists ci_autoimportqc");
                Tr_Session_Class::add_error('No file uploaded');
                echo '<meta http-equiv="refresh" content="1; url=admin.php?page=tox_results&tab=generator">';
                exit;
            } else {
                $wpdb->query("drop table if exists ci_autoimportqc");
                $has_txt_file   = false;
                $multicol       = false;
                // read data
                $fcount         = 0;
                if (empty($error)) {


                    $date_cols = array("AcquisitionDate&Time", "CollectionDate", "DOB", "Received");
                    $use_cols = array(
                        'SampleName',
                        'SampleID',
                        'Matrix',
                        'Validity',
                        'AnalyteName',
                        'ComponentName',
                        'CompoundName',
                        'Analyte',
                        'CalculatedConcentration',
                        'AnalytePeakArea',
                        'AnalyteConcentration',
                    );

                    $required_cols = array(
                        'SampleName',
                        array('ComponentName', 'CompoundName', 'AnalyteName', ' Analyte')
                    );

                    if ($type_import == 'dl') {
                        $required_cols[] = 'AnalytePeakArea';
                    } else {
                        $required_cols[] = array('CalculatedConcentration', 'FinalConcentration');
                    }

                    $save_cols = array_merge($date_cols, $use_cols);
                    $values = '';

                    $headers = array();


                    foreach ($uploaded as $file) {
                        $basefilename = basename($file);
                        tr_pdd_activity("Parsing file " . ++$fcount . "." . $basefilename . "...");
                        $row_data = array();
                        $ci_found_columns = array();
                        $instrument_col = false;
                        $doc_col = false;

                        if (stripos($file, '.txt') !== false) {
                            $has_txt_file = true;
                            $dt = file_get_contents($file);
                            $rows = explode("\n", $dt);

                            foreach ($rows as $row => $rowdt) {
                                $rowdt = str_replace(array("\t\"", "\"\t"), "\t", $rowdt);
                                $rowdt = explode("\t", $rowdt);

                                if ($row == 0 || !is_array($headers[$file])) {
                                    $headers[$file] = tr_pdd_check_header_result($rowdt, $required_cols);

                                    if (!isset($_SESSION['more_data_files'][$basefilename])) {
                                        if ($instrument_col !== false) {
                                            $_SESSION['more_data_files'][$basefilename] = array(
                                                'ins' => '',
                                                'doc' => '',
                                            );
                                            $instr = isset($rowdt[$instrument_col]) ? trim($rowdt[$instrument_col]) : '';
                                            if (!empty($instr)) {
                                                $_SESSION['more_data_files'][$basefilename]['ins'] = $instr;
                                            }

                                            $docf = isset($rowdt[$doc_col]) ? trim($rowdt[$doc_col]) : '';
                                            if (!empty($docf)) {
                                                $_SESSION['more_data_files'][$basefilename]['doc'] = $docf;
                                            }
                                            $instrument_col = 0;
                                            $doc_col = 0;
                                        } else {
                                            $lower_row = array_map('strtolower', $rowdt);
                                            $lower_row = array_map('trim', $lower_row);
                                            $instrument_col = array_search('instrument name', $lower_row);
                                            $doc_col = array_search('acquisition date', $lower_row);
                                        }
                                    }
                                } else {
                                    $row_data[$row] = $rowdt;
                                }
                            }
                        } else {


                            $filetype = PHPExcel_IOFactory::identify($file);

                            $objReader = PHPExcel_IOFactory::createReader($filetype);

                            if (stripos($file, '.csv') > 0) {
                                //check semicolon
                                $datafile           = file_get_contents($file);
                                $count_semicolon    = substr_count($datafile, ';');
                                $count_comma        = substr_count($datafile, ',');
                                $datafile           = '';
                                if ($count_semicolon > $count_comma) {
                                    $objReader->setDelimiter(';');
                                }
                            }


                            $objPHPExcel = $objReader->load($file);


                            $sheet = $objPHPExcel->getSheet(0);
                            $highestRow = $sheet->getHighestRow();
                            $highestColumn = $sheet->getHighestColumn();

                            for ($row = 1; $row <= $highestRow; $row++) {
                                $rowdt = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
                                $rowdt = $rowdt[0];

                                if ($row == 1 || !is_array($headers[$file])) {

                                    if (stripos($rowdt[0], 'Compound Method') !== false) {
                                        $multicol = true;
                                    } else  if (stripos($rowdt[0], 'Analyte') !== false && stripos($rowdt[1], 'qc1') !== false) {
                                        $multicol = true;
                                        $save_cols[] = 'mtestname';
                                        $save_cols[] = 'low';
                                        $save_cols[] = 'mid';
                                        $save_cols[] = 'high';
                                        $required_cols = array('mtestname', 'low', 'mid', 'high');
                                    }

                                    if ($multicol) {
                                        $headers[$file] = array();
                                        foreach ($rowdt as $ck) {
                                            if (stripos($ck, 'Compound Method') !== false || stripos($ck, 'Analyte') !== false) {
                                                $headers[$file][] = 'mtestname';
                                            } else {

                                                if (!empty($ck)) {
                                                    if (stripos($ck, 'low') !== false || stripos($ck, 'qc1') !== false) {
                                                        $ck = 'low';
                                                    } else if (stripos($ck, 'mid') !== false || stripos($ck, 'qc2') !== false) {
                                                        $ck = 'mid';
                                                    } else if (stripos($ck, 'high') !== false || stripos($ck, 'qc3') !== false) {
                                                        $ck = 'high';
                                                    } else {
                                                        $mid = $wpdb->get_var("select mid from ci_batches where assen = '{$ck}' ");
                                                        if ($mid > 0) {
                                                            $ck = 'mid_' . $mid;
                                                        }
                                                    }
                                                }
                                                $headers[$file][] = $ck;
                                            }
                                        }
                                    } else {
                                        $headers[$file] = tr_pdd_check_header_result($rowdt, $required_cols);
                                    }



                                    // var_dump($rowdt);
                                    //echo '<br><br>';
                                    if (!isset($_SESSION['more_data_files'][$basefilename])) {
                                        if ($instrument_col !== false) {
                                            $_SESSION['more_data_files'][$basefilename] = array(
                                                'ins' => '',
                                                'doc' => '',
                                            );
                                            $instr = isset($rowdt[$instrument_col]) ? trim($rowdt[$instrument_col]) : '';
                                            if (!empty($instr)) {
                                                $_SESSION['more_data_files'][$basefilename]['ins'] = $instr;
                                            }

                                            $docf = $doc_col !== false && isset($rowdt[$doc_col]) ? trim($rowdt[$doc_col]) : date_i18n('Y-m-d');
                                            if (!empty($docf)) {
                                                $_SESSION['more_data_files'][$basefilename]['doc'] = $docf;
                                            }
                                            $instrument_col = 0;
                                            $doc_col = 0;
                                        } else {
                                            $lower_row = array_map('strtolower', $rowdt);
                                            $lower_row = array_map('trim', $lower_row);
                                            $instrument_col = array_search('instrument name', $lower_row);
                                            $doc_col = array_search('acquisition date', $lower_row);
                                        }
                                    }
                                } else {
                                    $row_data[$row] = $rowdt;
                                }
                            }
                        }


                        $missing_columns = array();
                        //$ci_found_columns
                        foreach ($required_cols as $rcl) {
                            if (!is_array($headers[$file]) || !in_array($rcl, $headers[$file])) {

                                if (is_array($rcl)) {
                                    $rcl = $rcl[0];
                                }
                                if (in_array($rcl, $ci_found_columns)) {
                                    continue;
                                }

                                $missing_columns[] = $rcl;
                                tr_pdd_activity("<span style='color:red'>Missing Column: {$rcl}</span>");
                            }
                        }

                        if (count($missing_columns) > 0) {
            ?>
                            <script>
                                jQuery(function($) {
                                    $('#ajax-loader').hide();
                                })
                            </script>
            <?php
                            exit;
                        }



                        $filename = $file;
                        $expl = explode("/", $filename);
                        $file = end($expl);
                        $values .= $wpdb->prepare("('0','Report Table','%s','%s'),", $file, $file);
                        foreach ($row_data as $index => $row) {
                            $insert = array();
                            $c = 0;
                            foreach ($row as $r) {
                                if (in_array($headers[$filename][$c], $date_cols)) {

                                    if ($has_txt_file) {
                                        $r = gmdate('Y-m-d', strtotime($r));
                                    } else {
                                        $r = gmdate('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($r));
                                    }
                                    if ($r == '1970-01-01') $r = '';
                                }
                                if (in_array($headers[$filename][$c], $save_cols)) {
                                    $insert[$headers[$filename][$c]] = $r;
                                }
                                $c++;
                            }

                            $values .= $wpdb->prepare("('" . $index . "','','%s','%s'),", json_encode($insert), $file);

                            if ($index % 1000 == 0) { // run INSERT every 1000 rows
                                $wpdb->query("INSERT INTO ci_excel_{$wpdb->prefix} (`row`,`col`, `val`,`filename`) VALUES " . substr($values, 0, -1));
                                $values = "";
                            }
                        }
                        if (!empty($values)) $wpdb->query("INSERT INTO ci_excel_{$wpdb->prefix} (`row`,`col`, `val`,`filename`) VALUES " . substr($values, 0, -1));
                        $values = '';


                        //tr_pdd_activity("Done.");
                    }





                    foreach ($uploaded as $file) {
                        //copy a log file
                        $fname = basename($file);

                        if (stripos($fname, '.csv') > 0) {
                            preg_match("/-([0-9]+)\s*(.*)\./", $fname, $matches);
                            $batch_id = @$matches[1];
                            if (empty($batch_id)) {
                                preg_match("/Batch ([0-9]+)/", $fname, $matches_lab);
                                $batch_id = $matches_lab[1];
                            }
                            if (!empty($batch_id)) {
                                $lab_id = CI_Base_Lab::get_current_lab_id();
                                $old = $wpdb->get_row("select rf_ID from ci_reports_files 
                                WHERE rf_batch_id ='{$batch_id}' 
                                AND rf_file='{$fname}' 
                                AND rf_lab_site_id='{$lab_id}'  
                                and rf_btype='tox' ");

                                if ($old) {
                                    $wpdb->update('ci_reports_files', array('rf_count' => $old->rf_count + 1), array('rf_ID' => $old->rf_ID));
                                } else {

                                    $wpdb->insert('ci_reports_files', array(
                                        'rf_batch_id' => $batch_id,
                                        'rf_lab_site_id' => CI_Base_Lab::get_current_lab_id(),
                                        'rf_file' => $fname,
                                        'rf_count' => 1,
                                        'rf_btype' => 'tox',
                                    ));
                                }
                            }
                        }
                    }


                    //tr_pdd_activity("Generating DOCXs...");
                    echo "</ul>";
                } else {
                    echo $error;
                    exit;
                }
            }

            $parse_url = 'admin.php?page=' . $_GET['page'] . '&tab=generator&act=parse';
            if ($type_import) {
                $parse_url .= '&type=' . $type_import;
            }
            if (isset($_REQUEST['debug'])) {
                $parse_url .= '&debug';
            }
            if (isset($_POST['only_insert'])) {
                $parse_url .= '&only_insert';
            }
            ?>
    </div>

    <meta http-equiv="refresh" content="1; url=<?php echo $parse_url ?>">
    <?php
} else if ($act ==  'parse') {
    // var_dump($_SESSION['more_data_files']);

    $only_insert = isset($_GET['only_insert']) ? true : false;
    $overwritten = isset($_GET['overwritten']) ? true : false;

    $wpdb->suppress_errors(true);
    $current_lab        = CI_Base_Lab::get_current_lab();
    $current_lab_slug   = $current_lab['lab_dba_init'];
    $urines = $wpdb->get_results("select * from ci_urine_validity where u_status = 1");
    $urine_tests_all = array();
    foreach ($urines as $u) {
        $key = strtoupper($u->u_name);
        $urine_tests_all[$key] = $u->u_ID;
    }

    $ex_q = $wpdb->get_results("SELECT * FROM ci_excel_{$wpdb->prefix} ORDER BY id ASC", ARRAY_A);
    $report_file = '';
    $reports_files = array();

    foreach ($ex_q as $e) {
        if ($e['row'] == 0) $report_file = $e['val'];
        if (!empty($e['row'])) {
            $r = json_decode($e['val'], true);
            $r['filename'] = $e['filename'];

            if (isset($r['mtestname']) && isset($r['low'])) {
                foreach (array('low' => 'LQC', 'mid' => 'MQC', 'high' => 'HQC') as $k1 => $vl1) {
                    $r['SampleName'] = $vl1;
                    $r['ComponentName'] = $r['mtestname'];
                    $r['CalculatedConcentration'] = $r[$k1];
                    $rows[]  = $r;
                }
            } else {
                $rows[]  = $r;
            }
        }
    }

    $cutoffs = tr_pdd_get_cutoffs($btype);

    $pairs_q = $wpdb->get_results("SELECT LCASE(drug_1),LCASE(drug_2),LCASE(drug_3),LCASE(drug_4) FROM ci_drug_analyte_pairs", ARRAY_A);
    foreach ($pairs_q as $p) {
        for ($i = 0; $i < 4; $i++) {
            if (!empty($p[$i])) $drug_pairs[$p[$i]] = array($p[0], $p[1], $p[2], $p[3]);
        }
    }


    //get labs
    $labs = array();
    $rs_labs = $wpdb->get_results("select lab_ID, lab_dba_init from ci_labs where lab_status = 1");
    foreach ($rs_labs as $l) {
        $labs[$l->lab_ID] = $l->lab_dba_init;
    }


    if (is_array($rows)) {
        $instruments    = array();
        $data           = array();
        $exists_rows    = array();
        $list_passed_override = array();
        $labsbyfilename = array();

        foreach ($rows as $r) {

            $filename   = $r['filename'];
            $arr        = $r;
            $arr['filename'] = $filename;
            //fix data
            $arr['SampleName'] = str_replace('"', '', $arr['SampleName']);
            $arr['SampleName'] = strtoupper($arr['SampleName']);

            if (in_array(strtoupper($arr['SampleName']), array('CAL1', 'CAL2', 'CAL3', 'CAL4', 'CAL5', 'CAL6', 'MEOH'))) {
                continue;
            } else if (
                stripos($arr['SampleName'], 'Cal-') === 0
                || stripos($arr['SampleName'], '1cal-') === 0
                || stripos($arr['SampleName'], 'lod-') === 0
                || stripos($arr['SampleName'], '1lod-') === 0
                || stripos($arr['SampleName'], 'co-') === 0
                || stripos($arr['SampleName'], '1co-') === 0
                || stripos($arr['SampleName'], 'xco-') !== false
            ) {
                continue;
            }

            if (!isset($arr['ComponentName']) || empty($arr['ComponentName'])) {
                $arr['ComponentName'] = isset($arr['CompoundName']) ? $arr['CompoundName'] : '';
            }
            if (!isset($arr['ComponentName']) || empty($arr['ComponentName'])) {
                $arr['ComponentName'] = $arr['AnalyteName'];
            }

            if (!isset($arr['ComponentName']) || empty($arr['ComponentName'])) {
                $arr['ComponentName'] = $arr['Analyte'];
            }

            if (
                stripos($arr['SampleName'], 'Blank') !== false
                || stripos($arr['SampleName'], 'CAL ') !== false
                || empty($arr['ComponentName'])
            ) {
                continue;
            }

            //end fix

            $is_qc = stripos($arr['SampleName'], 'qc') !== false;
            if (isset($arr['mtestname'])) {
                $is_qc = true;
            }

            if ($type_import == 'qc' && !$is_qc) {
                //only import QC (empty SampleID)
                continue;
            }

            if ($is_qc) {
                $arr['SampleName'] = str_replace(
                    array(
                        'QC3',
                        'QC 3',
                        'QC2',
                        'QC 2',
                        'QC1',
                        'QC 1'
                    ),
                    array(
                        'HQC',
                        'HQC',
                        'MQC',
                        'MQC',
                        'LQC',
                        'LQC',
                    ),
                    $arr['SampleName']
                );
            }


            if (in_array($arr['SampleName'], array('QC HIGH', 'QCH'))) {
                $arr['SampleName'] = 'HQC';
            } else  if (in_array($arr['SampleName'], array('QC LOW', 'QCL'))) {
                $arr['SampleName'] = 'LQC';
            } else  if (in_array($arr['SampleName'], array('QC MEDIUM', 'QCM'))) {
                $arr['SampleName'] = 'MQC';
            }


            $arr['SampleName'] = trim($arr['SampleName']);
            $pkey = isset($arr['SampleID']) ? $arr['SampleID'] : '';
            if (empty($pkey)) {
                $pkey = $arr['SampleName'] . '_' . $arr['filename'];
            }

            if (empty($instruments[$filename])) {
                $fname = basename($filename);

                $mchine  = '';
                foreach ($all_instruments as $mc) {
                    if (stripos($fname, $mc) !== false) {
                        $mchine = $mc;
                        break;
                    } else if ($mc == 'LCMS1' && stripos($fname, 'LCMS_1') > 0) {
                        $mchine = $mc;
                        break;
                    }
                }

                if (empty($mchine) && isset($_SESSION['more_data_files'][$filename]) && isset($_SESSION['more_data_files'][$filename]['ins'])) {
                    $mchine = $_SESSION['more_data_files'][$filename]['ins'];
                }

                if (empty($mchine)) {
                    preg_match("/(MS[^\_\s\.]+)/", $fname, $mats);
                    $mchine = strtoupper(isset($mats[1]) ? $mats[1] : '');
                    if (empty($mchine)) {
                        preg_match("/\_([A-Z]{1,2}MS[^\_\s\.]*)/", $fname, $mats);
                        $mchine = strtoupper(isset($mats[1]) ? $mats[1] : '');
                    }
                }

                $instruments[$filename] = $arr['instrument'] = strtoupper($mchine);
            } else {
                $arr['instrument'] = $instruments[$filename];
            }

            if (empty($pkey)) continue;

            $pass_override = isset($_POST['override_pkeys'][$pkey]) && $_POST['override_pkeys'][$pkey] === '0';
            if ($pass_override) {
                $list_passed_override[$pkey] = $pkey;
                continue;
            }

            if (!isset($labsbyfilename[$filename])) {
                $labsbyfilename[$filename] = '';
                foreach ($labs as $lid => $labinit) {
                    if (stripos($filename, $labinit) !== false) {
                        $labsbyfilename[$filename]  = $lid;
                        break;
                    }
                }
            }

            if (empty($arr['Sublab']) || empty($arr['Sublab'])) {
                if (!empty($labsbyfilename[$filename])) {
                    $arr['lab_id'] = $labsbyfilename[$filename];
                    $arr['Lab'] = $labs[$arr['lab_id']];
                    $arr['Sublab'] = $labs[$arr['lab_id']];;
                }
            }



            $data[$pkey][] = $arr;

            $data[$pkey][0]['files'][$arr['filename']] = $arr['filename'];



            if (empty($data[$pkey][0]['Lab']) && !empty($arr['Lab'])) {
                $data[$pkey][0]['Lab'] = $arr['Lab'];
            }

            if (empty($data[$pkey][0]['Sublab']) && !empty($arr['Sublab'])) {
                $data[$pkey][0]['Sublab'] = $arr['Sublab'];
            }

            if (stripos($filename, 'oral') > 0) {
                $data[$pkey][0]['Matrix'] = 'Oral';
            }

            if (!isset($data[$pkey][0]['report'])) {
                $assen = $data[$pkey][0]['SampleName'];
                $is_qc = stripos($assen, 'qc') !== false;
                if (!isset($data[$pkey][0]['is_qc'])) {
                    $data[$pkey][0]['is_qc'] = $is_qc;
                }
                if (!$is_qc) {
                    $report = $wpdb->get_row($wpdb->prepare("select * from ci_reports
                        where  patient_id=%s ", $assen), ARRAY_A);

                    $data[$pkey][0]['report'] = $report;

                    if ($report && !empty($report['upload_files'])) {
                        $exists_rows[$pkey] = $assen;
                    }
                }
            }
        }

        if (isset($_REQUEST['debug2'])) {
            var_dump($data);
            exit;
        }

        if (count($exists_rows) > 0 && !isset($_REQUEST['pass_override'])) {
    ?>
            <div class="wrap">
                <div class="form-wrap center">
                    <form method="post">
                        These has already been resulted and you are uploading its data again. (check if want overridden)
                        <br><br>
                        <ol>
                            <?php
                            foreach ($exists_rows as $pkey => $assen) {
                            ?>
                                <li class="w100 aleft">
                                    <input type="hidden" name="override_pkeys[<?php echo $pkey ?>]" value="0" />
                                    <label>
                                        <input type="checkbox" name="override_pkeys[<?php echo $pkey ?>]" value="1" />
                                        <?php echo $assen ?>
                                    </label>
                                </li>
                            <?php
                            }
                            ?>
                        </ol>
                        <br>
                        <div class="frow">
                            <input type="submit" class="button-primary" name="pass_override" value="Continue Import" />
                        </div>
                    </form>
                </div>
            </div>
            <style>
                form .w100 {
                    width: 200px;
                    margin: 0 auto;
                }

                form .aleft {
                    text-align: left;
                }
            </style>
        <?php
            return;
        }
    } else {
        $url = 'admin.php?page=' . $_GET['page'] . '&tab=generator&e=2';
        ?>
        <meta http-equiv="refresh" content="1; url=<?php echo $url ?>">
    <?php
        exit;
    }

    $errors         = array();
    $report_file    = '';
    $check_file_string = '';
    $insert_fail    = array();
    $count_row_total = count($list_passed_override);
    $count_row_skipped = count($list_passed_override);
    $count_row_inserted = 0;
    $count_row_updated = 0;
    $log_errors     = array();
    $update_ids     = array();
    $b_positions    = array();
    $batch_id       = '';



    // get report info
    foreach ($data as $pkey => $arr) {
        $count_row_total++;

        $report_these_drugs = array();
        $d = $arr[0];

        $d['files'] = (array)$d['files'];
        $upload_files = implode(',', $d['files']);
        $report_file = $d['filename'];

        //get filename
        if ($upload_files != $check_file_string) {
            $check_file_string = $upload_files;

            $batch_id = '';

            foreach ($d['files'] as $fna) {
                //get batch_id from $fna
                preg_match("/([0-9]{8})[a-zA-Z\.\_\s]+/", $fna, $matches);
                if (isset($matches[1]) && empty($batch_id)) {
                    $batch_id = @$matches[1];
                    if (substr($batch_id, 0, 4) < 2000) {
                        $batch_id = substr($batch_id, 4, 4) . substr($batch_id, 0, 2) . substr($batch_id, 2, 2);
                    }
                }
                if (empty($batch_id)) {
                    preg_match("/Batch ([0-9]+)/", $fna, $matches_lab);
                    $batch_id = isset($matches_lab[1]) ? $matches_lab[1] : '';
                }

                if (!empty($batch_id)) {
                    break;
                }
            }

            if (empty($batch_id)) {
                if (isset($_SESSION['more_data_files'][$filename]) && isset($_SESSION['more_data_files'][$filename]['doc'])) {
                    $doc = $_SESSION['more_data_files'][$filename]['doc'];
                    if (!empty($doc)) {
                        $batch_id = date('Ymd', strtotime($doc));
                    }
                }
            }
        }



        if (empty($d['Lab']) && !empty($d['Sublab'])) {
            $d['Lab'] = $d['Sublab'];
        }


        if (!empty($current_lab_slug) && empty($d['Lab'])) {
            $d['Lab']  = $current_lab_slug;
        }

        if (!isset($d['lab_id']) || empty($d['lab_id'])) {
            $d['lab_id'] = CI_Base_Lab::get_current_lab_id();
        }

        $d['Lab'] = preg_replace("/^([^\s]{3})R$/", '$1', $d['Lab']);
        // var_dump($d);exit;
        if (empty($d['Patient No']) && !empty($d['Patient No.'])) {
            $d['Patient No'] = $d['Patient No.'];
        }
        if (empty($d['batch_id'])) $d['batch_id'] = $batch_id;

        $batch = $wpdb->get_row("select batch_id,mid from ci_batches where assen = '{$d['SampleName']}' ", ARRAY_A);
        if (!empty($batch['batch_id'])) {
            $d['batch_id'] = $batch['batch_id'];
        }


        if (count($arr) == 0) {
            $count_row_skipped++;
            continue;
        }

        if (empty($d['batch_id'])) {
            $d['batch_id'] = date_i18n('Ymd');
        }


        //log files
        if (!isset($reports_files[$d['batch_id']]) || !is_array($reports_files[$d['batch_id']])) {
            $reports_files[$d['batch_id']] = array();
        }
        $reports_files[$d['batch_id']] = array_unique(array_merge($reports_files[$d['batch_id']], $d['files']));

        $insert = array(
            'batch_id'      => intval($d['batch_id']),
            'patient_name'  => isset($d['SampleName']) ? $d['SampleName'] : '',
            'patient_id'    => $d['SampleName'],
            'old_barcode'   => isset($d['SampleID']) ? $d['SampleID'] : '',
            'acquisition_date' => isset($d['AcquisitionDate&Time']) ? $d['AcquisitionDate&Time'] : '',
            'collection_date' => isset($d['CollectionDate']) ? $d['CollectionDate'] : '',
            'sublab'        => $d['Lab'],
            'validity'      => isset($d['Validity']) ? (bool)$d['Validity'] : false,
            'author_id'     => $user_id,
            'lab_id'        => $d['lab_id'],
            'created'       => $created,
            'modified'      => time(),
            'created_date'  => $created_date,
            'upload_files'  => $upload_files,
        );

        $barcode = $insert['patient_id'];

        if ($insert['patient_id'] == '90:10 D:L') {
            continue;
        }


        if (!empty($d['instrument'])) {
            $insert['instrument'] =  $d['instrument'];
        }
        if (!empty($d['Inst.']) && $d['Inst.'] != 'MS') {
            $insert['instrument'] =  $d['Inst.'];
        }
        $no_instrument = false;
        if (isset($insert['instrument']) && ($insert['instrument'] == 'ETG' || $insert['instrument'] == 'ETS')) {
            $no_instrument = true;
        } else if (isset($insert['instrument']) && count($all_instruments) > 0 && !in_array($insert['instrument'], $all_instruments)) {
            $no_instrument = true;
        } else if (!isset($insert['instrument']) || empty($insert['instrument']) || $insert['instrument'] == 'MS') {
            $insert['instrument'] = 'MS1';
        }


        if (empty($insert['acquisition_date']) && !empty($insert['batch_id'])) {
            if ($insert['batch_id'] > 20160101) {
                $acq_date = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", '$1-$2-$3', $insert['batch_id']);
            } else {
                $acq_date = preg_replace("/([0-9]{2})([0-9]{2})([0-9]{4})/", '$3-$1-$2', $insert['batch_id']);
                $insert['batch_id'] = str_replace('-', '', $acq_date);
            }
            $insert['acquisition_date'] = $acq_date;
        }



        //fix not null
        $insert['lab_id'] .= '';

        //check exists
        $report_id = 0;
        $is_qc = $d['is_qc'];
        $qc_number = 0;

        if ($is_qc) {
            if (strpos($insert['patient_name'], '-1') > 0) {
                $insert['patient_name'] = str_replace('-1', '', $insert['patient_name']);
                $qc_number = 1;
            } else if (strpos($insert['patient_name'], '-2') > 0) {
                $insert['patient_name'] = str_replace('-2', '', $insert['patient_name']);
                $qc_number = 2;
            } else if (strpos($insert['patient_name'], '-3') > 0) {
                $insert['patient_name'] = str_replace('-3', '', $insert['patient_name']);
                $qc_number = 3;
            }
        }

        if (stripos($insert['patient_id'], 'qc') !== false) {
            if (stripos($insert['patient_id'], 'low QC') !== false) {
                $insert['patient_id'] = 'LQC';
                $insert['patient_name'] = 'LQC';
            } else if (stripos($insert['patient_id'], 'high QC') !== false) {
                $insert['patient_id'] = 'HQC';
                $insert['patient_name'] = 'HQC';
            }
            $insert['patient_id'] = $insert['patient_id'] . '_' . (isset($insert['instrument']) ? $insert['instrument'] : '');
            $is_qc = true;
        }




        $insert['patient_id'] = trim($insert['patient_id']);


        $rs     = false;
        $report = false;

        if (isset($d['report']) && !empty($d['report'])) {
            $report = $d['report'];
        } else if (!$is_qc) {
            $report = $wpdb->get_row($wpdb->prepare("select * from ci_reports
                        where  patient_id=%s ", $insert['patient_id']), ARRAY_A);
        } else {
            $report = $wpdb->get_row($wpdb->prepare("select * from ci_reports
                        where  patient_id=%s 
                        and batch_id = %s 
                        and sublab = %s ", $insert['patient_id'], $insert['batch_id'], $insert['sublab']), ARRAY_A);
        }

        if (!$report) {
            $rs = $wpdb->insert('ci_reports', $insert);
            $report_id = $wpdb->insert_id;
            if ($rs === false) {
                //echo 'Error';exit;
                $insert['error'] = $wpdb->last_error;
                $insert_fail[] = $insert;
                $count_row_skipped++;
            } else {
                $count_row_inserted++;
            }
        } else {

            if ($only_insert) {
                $count_row_skipped++;
                continue;
            }
            $report_id  = $report['id'];

            unset($insert['lab_id']);
            unset($insert['author_id']);
            unset($insert['created']);

            if ($no_instrument) {
                unset($insert['instrument']);
            }


            $insert['upload_files'] .= ',' . $report['upload_files'];
            $insert['upload_files'] = str_replace(', ', ',', $insert['upload_files']);
            $insert['upload_files'] = explode(',', $insert['upload_files']);
            $insert['upload_files'] = array_unique($insert['upload_files']);
            $insert['upload_files'] = implode(',', $insert['upload_files']);

            if (in_array($type_import, array('screening', 'dl'))) {
                $rs = true;
            } else {
                $rs = $wpdb->update('ci_reports', $insert, array('id' => $report_id));
            }

            $count_row_updated++;
            $update_ids[] = $report_id;
        }


        if ($report_id == 0) {
            continue;
        }

        //import screenings

        if ($type_import == 'screening') {

            $has_urine = false;

            foreach ($arr as $ii => $drug) {

                if (!isset($drug['ComponentName']) || empty($drug['ComponentName'])) {
                    $drug['ComponentName'] = trim($drug['AnalyteName']);
                }
                if (!isset($drug['CalculatedConcentration']) || empty($drug['CalculatedConcentration'])) {
                    $drug['CalculatedConcentration'] = trim($drug['AnalyteConcentration']);
                }
                $testslug = strtoupper($drug['ComponentName']);
                $testslug = trim($testslug);
                $testid = $urine_tests_all[$testslug];
                if ($testid == 0) {
                    $testslug = str_replace(' 1', '', $testslug);
                    $testslug = trim($testslug);
                    $testid = $urine_tests_all[$testslug];
                }
                $conc   = $drug['CalculatedConcentration'];
                $conc = str_replace(' ', '', $conc);
                $conc = preg_replace("/[^0-9-.,]/", '', $conc);
                if ($testid > 0 && $batch['mid'] > 0) {
                    $rsc = $wpdb->query("INSERT INTO ci_result_test_speciments (mid,testid,conc,api_type) 
									values ('{$batch['mid']}','{$testid}','{$conc}',1) 
									ON DUPLICATE KEY UPDATE conc = '{$conc}', api_type = 1 ");
                    if ($rsc === false) {
                        echo 'error:';
                        var_dump($wpdb->last_error);
                    }
                    $has_urine = true;
                } else if ($testid == 0) {
                    //not exist
                    $errors[$testslug . 'screening'] = 'Test: ' . $testslug . ' not exists<br>';
                    $count_row_skipped++;
                }
            }

            trpdd_log_user($user_id, 'uploaded_screening_rs', $upload_files, $batch['mid'], 'tox', '', '', $barcode);
        } else if ($type_import == 'dl') {
            $update = array();
            foreach ($arr as $ii => $drug) {
                $drugname = $drug['ComponentName'];
                $conc = $drug['Analyte Peak Area'];
                if (empty($drugname)) {
                    $drugname = trim($drug['AnalyteName']);
                }
                $drugname = trim(strtolower($drugname));

                if ($drugname == 'd' || stripos($drugname, 'd-') !== false) {
                    $update['d'] = $conc;
                } else if ($drugname == 'l' || stripos($drugname, 'l-') !== false) {
                    $update['l'] = $conc;
                }
            }

            if (count($update) > 0 && isset($update['d']) && isset($update['l'])) {
                $update['report_id'] = $report_id;
                $update['d'] = floatval($update['d']);
                $update['l'] = floatval($update['l']);
                $total = $update['d'] + $update['l'];
                if ($total > 0) {
                    $update['d'] = round($update['d'] / $total * 100);
                    $update['l'] = 100 - $update['d'];
                } else {
                    $update['d'] = 0;
                    $update['l'] = 0;
                }

                $exists = $wpdb->get_var("select report_id from ci_result_metha where report_id = {$report_id} ");
                if ($exists) {
                    $wpdb->update('ci_result_metha', $update, array('report_id' => $report_id));
                } else {
                    $wpdb->insert('ci_result_metha', $update);
                }

                trpdd_log_user($user_id, 'uploaded_dl_rs', $upload_files, $batch['mid'], 'tox', '', '', $barcode, 'Result');
            }
        } else {
            $has_pending = $wpdb->get_var("select count(*) from ci_results WHERE report_id ={$report_id} and `pos-neg`='PEN' ");
            foreach ($arr as $drug) {

                if (!isset($drug['ComponentName']) || empty($drug['ComponentName'])) {
                    $drug['ComponentName'] = trim($drug['AnalyteName']);
                }

                if (!isset($drug['CalculatedConcentration']) || empty($drug['CalculatedConcentration'])) {
                    $drug['CalculatedConcentration'] = trim($drug['AnalyteConcentration']);
                }

                if (!isset($drug['instrument']) || empty($drug['instrument'])) {
                    $drug['instrument'] = $insert['instrument'];
                }

                tr_pdd_checkDrug($report_id, $drug, false, $is_qc, $qc_number);
            }


            //user log
            if (isset($batch['mid'])) {
                trpdd_log_user($user_id, 'uploaded_rs', array('assen' => $barcode, 'file' => basename($filename)), $batch['mid'], 'tox', '', '', $barcode);
            }
        }
    }
    if ($wpdb->error) {
        echo $wpdb->error;
        exit;
    }



    //save log files
    $lab_id = CI_Base_Lab::get_current_lab_id();
    if ($type_import == 'qc') {
        $reports_files = array();
    }
    foreach ($reports_files as $bid => $files) {
        $log_fs = $wpdb->get_results("select * from ci_reports_files 
                    where rf_batch_id='{$bid}' 
                    and rf_lab_site_id='{$lab_id}'");
        $oldfs = array();
        foreach ($log_fs as $v) {
            $oldfs[$v->rf_file] = $v;
        }

        foreach ($files as $f) {
            if (isset($oldfs[$f])) {
                $old = $oldfs[$f];
                $wpdb->update('ci_reports_files', array(
                    'rf_count' => $old->rf_count + 1
                ), array('rf_ID' => $old->rf_ID));
            } else {

                $wpdb->insert('ci_reports_files', array(
                    'rf_batch_id' => $bid,
                    'rf_lab_site_id' => $lab_id,
                    'rf_file' => $f,
                    'rf_count' => 1
                ));
            }
        }
    }
    if (count($insert_fail) > 0) {
        trpdd_log_form('result_error', $insert_fail);
    }


    //add cron for clean results
    $nextcron = wp_next_scheduled('ci_cron_clean_results');
    if ($nextcron == 0 || $nextcron > time() + 360) {
        wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array(0));
    }

    $wpdb->query("drop table ci_excel_{$wpdb->prefix}");

    if (count($errors) > 0) {
        foreach ($errors as $error) {
            echo '<li style="color:red">Error: ' . $error . '</li>';
        }
    }
    ?>
    <div class="wrap">
        <div class="form-wrap">
            <h2>Done!</h2>
            <ul>
                <li>Total: <?php echo $count_row_total ?></li>
                <li>Inserted: <?php echo $count_row_inserted ?></li>
                <li>Updated: <?php echo $count_row_updated ?></li>
                <li>Skipped or Error: <?php echo $count_row_skipped ?></li>
            </ul>


            <a href="admin.php?page=<?php echo $_GET['page'] ?>&tab=list&created=<?php echo $created ?>">Click here to view Results</a>
            <?php
            if (isset($_REQUEST['autoimportqc'])) {
                echo '<meta http-equiv="refresh" content="1; url=admin.php?page=' . $_GET['page'] . '&tab=generator&type=qc&autoimportqc&act=ci_doctor_process">';
            }
            ?>
        </div>
    </div>
<?php
    return;
} else {

    $_SESSION['more_data_files'] = array();

?>
    <h2 class="center">Upload Raw Data</h2>

    <?php if ((isset($_GET['s']) && $_GET['s'] == 1) || !empty($saved)) { ?>
        <div class="updated">
            <p>Success!<?php if (!empty($saved)) echo " " . $saved; ?></p>
        </div><?php } ?>
    <?php if (isset($_GET['e']) && $_GET['e'] == 1) { ?>
        <div class="error">
            <p>Error! Datasheet did not import directly or no data was
                provided<?php if (!empty($_GET['msg'])) echo "<br/>MySQL error: &quot;" . htmlspecialchars($_GET['msg']) . "&quot;"; ?></p>
        </div><?php } ?>
    <?php if (isset($_GET['e']) && $_GET['e'] == 2) { ?>
        <div class="error">
            <p>Error! Datasheets were successfully uploaded, but the server lost the database
                connection during processing. Please try to upload again with one less datasheet.</p>
        </div><?php } ?>
    <?php if (isset($_GET['e']) && $_GET['e'] == 0) { ?>
        <div class="error">
            <p>Error! Datasheet was not properly uploaded to the server. Uploads cannot
                exceeded 500 MB per submission.</p>
        </div><?php } ?>
    <div class="form-wrap center">
        <form enctype="multipart/form-data" method="post">
            <input type="hidden" name="act" value="ci_doctor_process">

            <p class="alert message warning">Please only upload CSV files!</p>

            <input type="file" id="upload-files" name="files[]" accept="text/csv" multiple />


            <input type="submit" value="Upload" class="button-primary" />


            <br>
            <div style="margin:30px 0">
                <label class="inline">
                    <input type="radio" name="type" value="" checked /> Normal</label>
                <label class="inline">
                    <input type="radio" name="type" value="screening" /> Import Screening</label>
                <label class="inline">
                    <input type="radio" name="type" value="qc" />Import QC</label>

                <label class="inline">
                    <input type="radio" name="type" value="dl" />Import D/L</label>
            </div>

            <label class="inline">
                <input type="checkbox" name="only_insert" value="1" /> Only Insert New</label>

            <p><em>To upload multiple files, hold down SHIFT while clicking to select a group or CTRL to
                    select multiple files individually.</em></p>
        </form>
    </div>

    <style>
        form label.inline {
            display: inline-block !important;
            padding: 2px 8px;
        }
    </style>
<?php
}
