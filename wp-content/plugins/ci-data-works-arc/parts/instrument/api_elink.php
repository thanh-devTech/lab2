<?php
global $wpdb;
$log_dir = WP_CONTENT_DIR . '/log/elink/';
include(TRCPDD_PATH . 'api/ci_api_elink.php');
//error_reporting(E_ALL);
if (isset($_GET['tr_debug'])) {
    ini_set('display_errors', '1');
}

$covid_status = array(
    '0' => 'Negative',
    '1' => 'Positive',
    '2' => 'ReRun',
    '4' => 'Inconclusive'
);

if (isset($_POST['options'])) {
    $options = $_POST['options'];
    if (empty($options['cron'])) {
        $options['cron'] = 2;
    }
    if (isset($options['msh4'])) {
        $options['msh4'] = trim($options['msh4']);
    }
    $old_options = get_option('_tr_elink_settings', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api__tr_elink_settings', $bkdata);
    update_option('_tr_elink_settings', $options);

    Tr_Session_Class::add("Saved Settings Successfully");

    wp_clear_scheduled_hook('cron_api_elink');
    if ($options['active']) {
        wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_api_elink');
    }
}
$archive_date       = tr_get_option('ci_pdf_data', 'archive_date', '');
$options = get_option('_tr_elink_settings', array());
if (empty($options['cron'])) {
    $options['cron'] = 2;
}
try {
    $elink = new CI_Api_ELINK($options);
} catch (Exception $e) {
    var_dump($e);
}
if (!empty($elink->last_error)) {
    Tr_Session_Class::add_error($elink->last_error);
    var_dump(file_get_contents('https://www.mylabsync.com/WebServices/eLink_LIS/eLink_LIS.asmx?wsdl'));
}
$elink->is_test = !isset($options['active']) || !$options['active'];

if ($act == 'test') {
    include('../inc/ci_data_work_xml.php');
    $xml = new CI_Data_Work_Xml();
    $rs = $elink->RequestOrders(1, true);
    $rs = $xml->objToXML($rs);

    echo '<textarea style="width:100%;height:300px">' . $rs . '</textarea>';
    exit;
} else if ($act == 'test_send_rs') {
    $xml = new CI_Data_Work_Xml();
    $lab = $wpdb->get_row("select * from ci_labs order by lab_ID asc limit 1");
    $rs = '';
    try {
        $r = (object) array(
            're_ID' => '1',
            'doc' => '2020-11-13 14:05:18',
            'assen' => 'DEMO123456',
            'patient_name' => 'Lab, Testus',
            'pa_first' => 'Testus',
            'pa_last' => 'Lab',
            'pa_ssn' => '123456789',
            'pa_mrn' => '111222333',
            'pa_code' => '333222111',
            'pa_phone' => '12485551212',
            'pa_gender' => 'F',
            'pa_dob' => '1945-09-02',
            'pa_address' => '1234 AnyStreet',
            'pa_city' => 'AnyCity',
            'pa_state' => 'MI',
            'pa_zip' => '48101',
            'pa_email' => '48101@gmail.com',

            'doc_first' => 'Doctor First',
            'doc_last' => 'Doctor Last',
            'doc_npi' => '1234567',

            'pos_neg' => 'POS',
            'approve_date' => '2020-11-14 05:07:37',
            'result_status' => 'Final',
            'type_of_specimen' => 'nasopharyngeal swab',

            'lab_name' => $lab->lab_name,
            'lab_clia' =>  $lab->lab_clia,
            'lab_loinc_code' => '94533-7',
            'lab_loinc_desc' => 'SARS coronavirus 2 N gene [Presence] in Respiratory specimen by NAA with probe detection',

            'cli_name' => 'County Health Care',
            'cli_address' => '1234 AnyRoad',
            'cli_city' => 'AnyCity',
            'cli_state' => 'MI',
            'cli_zip' => '48100',
            'cli_phone' => '13135551212',

            'mo_test_ID' => 7,
            'mo_set_ID' => 7,
            'mo_control_ID' => 7,
            'mo_patient_ID' => 7,
            'mo_peg_ID' => 7,
        );
        $data = array();
        $data[] = $elink->build_msg($r);
        $rs = $elink->SubmitResults($data);

        $rs = $xml->objToXML($rs);
    } catch (Exception $e) {
        var_dump($e);
    }


    echo '<textarea style="width:100%;height:300px">' . $rs . '</textarea>';
    exit;
} else if ($act == 'sync') {
    $rs = 0;
    try {
        $rs = $elink->import_reqs(100);
    } catch (Exception $e) {
        //print($elink->client->__getLastResponse());
        var_dump($e);
    }
    echo 'count: ' . $rs . '<br>';
    echo (nl2br($elink->log));
    exit;
} else if ($act == 'sync_reports') {
    try {
        $rs = $elink->sync_reports();
    } catch (Exception $e) {
        var_dump($e);
    }
    echo $rs . '<br>';
    echo (nl2br($elink->log));
    exit;
} else if ($act == 'log') {

    $joins = '';
    $fields = '';
    $where = " 1 ";
    if (!empty($archive_date)) {
        $where .= " and ba.doc >' {$archive_date}' ";
    }
    if (isset($_GET['debug2'])) {
        $joins .= " join ci_patients pa ON pa.pa_ID = ba.patient_id ";
        $joins .= " join ci_insurances ins ON ins.ins_ID = ba.insurance1 ";
        $fields .= ", pa.pa_first, pa.pa_last, ins.ins_first, ins.ins_last, ins.ins_ID ";
    }
    if (!empty($_REQUEST['s'])) {
        $where .= " and ba.assen like '{$_REQUEST['s']}%' ";
    }

    $row_per_page = 200;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_pcr ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid and mo_file_id='{$elink->api_id}'
	                                where {$where}"));
    $offset = $pagination->offset();

    $rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.patient_name,
                                ba.doc,
                                ba.dagnostic_codes1,
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                o.mo_status,
                                o.mo_ID
                                {$fields}
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and mo_file_id='{$elink->api_id}'
                            {$joins}
                            where {$where} 
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");

    ob_start();

?>
    <div>
        <input type="search" id="assen_search">
        <a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
    </div>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width:60px">#</th>
                <th>Accession</th>
                <th>Patient</th>
                <th style="width:60px">Resulted</th>
                <th style="width:70px">Status</th>
                <th style="width:50px;">Sent</th>
                <th>Date/time</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($rows as $row) {
                if (isset($_GET['debug2'])) {
                    if (
                        strtolower($row->pa_first) == strtolower($row->ins_last)
                        && strtolower($row->pa_last) == strtolower($row->ins_first)
                        && !empty($row->pa_first)
                        && !empty($row->pa_last)
                    ) {
                        $wpdb->update('ci_insurances', array(
                            'ins_first' => $row->pa_first,
                            'ins_last' => $row->pa_last,
                        ), array('ins_ID' => $row->ins_ID));
                    }
                }

                if (isset($_GET['fix_code'])) {
                    $filepath = WP_CONTENT_DIR . '/log/elink/' . $row->mo_ID . '.hl7';
                    $arr = $elink->hl7_to_array(file_get_contents($filepath));
                    $code = $arr['DG1']['DG1.3']['DG1.3.1'];
                    if (!empty($code) && $code != $row->dagnostic_codes1) {
                        $wpdb->update('ci_batches_pcr', array('dagnostic_codes1' => $code), array('mid' => $row->mid));
                    }
                }
            ?>
                <tr class="childp" data-id="<?php echo $row->id ?>">
                    <td><?php echo $row->mid ?></td>
                    <td><a target="_blank" href="?page=ci_batches_view&act=edit&id=pcr_<?php echo $row->mid ?>&"><?php echo $row->assen ?></a></td>
                    <td><?php echo $row->patient_name ?></td>
                    <td><a href="?page=pcr_results&act=edit&id=<?php echo $row->id;
                                                                if (isset($_GET['debug'])) echo '&createnew=' . $row->mid ?>" target="_blank">
                            <?php echo $row->estatus == 'C' ? 'Yes' : (isset($_GET['debug']) ? '.' : '') ?>
                        </a>
                    </td>
                    <td>
                        <?php
                        if (!is_null($row->covid_pos)) {
                            echo $covid_status[$row->covid_pos];
                        }
                        ?>
                    </td>
                    <td><a class='resend' href="#"><?php echo $row->mo_status > 0 ? 'Yes' : '' ?></a></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($row->doc)) ?></td>
                    <td><?php
                        echo '<a target=_blank href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'viewrshl7', 'fid' => $row->mo_ID), false) . '">View Hl7</a>';
                        ?></td>
                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    <?php
    $content = ob_get_clean();
    if (isset($_GET['trajax'])) {
        $content = str_replace('class="', 'class="show_popup ', $content);
        $content = str_replace('class=\'', 'class=\'show_popup ', $content);
    }
    echo $content;
    exit;
} else if ($act == 'send_result' || $act == 'rebuild') {
    $return = array('status' => 'ok', 'msg' => '');

    try {
        $id = $_REQUEST['id'];

        $elink->debug = true;
        //$elink->is_Test = true;
        $rs = $elink->sync_reports(1, $id);
        if (!$rs) {
            $return['status'] = 'error';
            $return['msg'] = $elink->log;
        }
    } catch (Exception $e) {
        $return['status'] = 'error';
        $return['msg'] = $e->getMessage();
    }
    if($act == 'rebuild')
    {
        echo '<meta http-equiv="refresh" content="0; url='.Tr_Base_Class_V6::link(array('act' => 'viewrshl7'), false).'">';
        exit;
    }
    wp_send_json($return);
    exit;
} else if ($act == 'parse_hl7') {
    if (!empty($_POST['hl7'])) {
        $_POST['hl7'] = stripslashes($_POST['hl7']);
        $arr = $elink->hl7_to_array($_POST['hl7']);
        echo '<textarea style="width:100%;height:500px">';
        var_export($arr);
        echo '</textarea>';

        $homephone    = '';
        $phone        = '';
        $email        = '';
        if (is_array($arr['PID']['PID.13']['PID.13.1'])) {
            foreach ($arr['PID']['PID.13'] as $arr13) {
                if ($arr13[2] == 'CP') {
                    $phone = $arr13[0];
                } else if ($arr13[2] == 'PH') {
                    $homephone = $arr13[0];
                } else if ($arr13[2] == 'NET') {
                    $email = $arr13[3];
                }
            }
            if (empty($phone)) {
                $phone = $homephone;
            }
        } else {
            $phone        = trim($arr['PID']['PID.13']['PID.13.1']);
            $email        = trim($arr['PID']['PID.13']['PID.13.2']);
        }
        echo '<br><br>';
        echo 'hp: ' . $homephone . '<br>';
        echo '$phone: ' . $phone . '<br>';
        echo '$email: ' . $email . '<br>';
    }
    ?>
    <form method="post">
        <textarea name="hl7" style="width:100%;height:300px"><?php echo $_POST['hl7'] ?></textarea>
        <br>
        <input type="submit" class="button" value="Parse" />
    </form>
<?php
    exit;
} else if ($act == 'reimport') {

    $dbfile = $wpdb->get_row("select o.*,
		                        ba.assen
		                     from ci_sync_api_orders o 
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid and o.mo_btype = 'pcr'
		                     where mo_ID = {$_REQUEST['fid']}");

    $filepath = WP_CONTENT_DIR . '/log/elink/' . $_REQUEST['fid'] . '.hl7';
    $msg_id = $dbfile->mo_test_ID;
    $msg = file_get_contents($filepath);
    if (!empty($msg_id)) {
        var_dump($msg_id);
        var_dump($msg);
        echo '<br><br>';
        $rs = $elink->import_req_file($msg_id, $msg);
        echo '<br><br>';
        var_dump($rs);
    }


    exit;
} else if ($act == 'viewrshl7') {
    try {
        $hl7_parser = new CI_HL7_Parser();

        $dbfile = $wpdb->get_row("select o.*,
            ba.assen,
            re.id
        from ci_sync_api_orders o 
        join ci_batches_pcr ba ON ba.mid = o.mo_mid and o.mo_btype = 'pcr'
        left JOIN ci_reports_pcr re ON re.patient_id = ba.assen
        where mo_ID = {$_REQUEST['fid']}");


        $filepath = WP_CONTENT_DIR . '/log/elink/' . $_REQUEST['fid'] . '.hl7';
        $rsfile = WP_CONTENT_DIR . '/log/elink/' . $_REQUEST['fid'] . '_rs.hl7';

        $reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);
        echo 'In file.:' . $filepath;
        echo '   <a href="' . $reimportlink . '">reimport</a>';


        $hl7 = file_get_contents($filepath);
        echo '<textarea style="width:2000px;height:300px">';
        echo esc_textarea($hl7);
        echo '</textarea>';
        if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
            $parsed = $hl7_parser->parse_hl7($hl7);
            if (!empty($parsed) && !isset($parsed['ERROR'])) {
                echo $hl7_parser->render_html($parsed);
            }
        }


        if (file_exists($rsfile)) {
            $reimportlink = Tr_Base_Class_V6::link(array('act' => 'rebuild', 'id' => $dbfile->id), false);
            echo '  -   <a href="' . $reimportlink . '">rebuild</a> - ';
            $hl7 = file_get_contents($rsfile);
            echo '<textarea style="width:2000px;height:300px">';
            echo esc_textarea($hl7);
            echo '</textarea>';
            if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
                $parsed = $hl7_parser->parse_hl7($hl7);
                if (!empty($parsed) && !isset($parsed['ERROR'])) {
                    echo $hl7_parser->render_html($parsed);
                }
            }
        }
    } catch (Exception $e) {
        Tr_Session_Class::add_error($e->getMessage());
    }
    Tr_Session_Class::show();
    exit;
} else if ($act == 'list_elinkid') {

    $list = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cast(cli_elink_id as unsigned) asc");
    $labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
?>
    <table id="elinktbl" class="posts wp-list-table widefat fixed hover_tbl">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Clinic</th>
                <th style="width:100px">ElinkID</th>
                <th style="width:100px">ElinkID 2</th>
                <th style="width:100px">Lab</th>
            </tr>
        </thead>
        <?php
        foreach ($list as $row) {
        ?>
            <tr class="datarow" data-id="<?php echo $row->cID ?>">
                <td><?php echo $row->cID ?></td>
                <td><?php echo $row->cli_clinic ?></td>
                <td><input type="text" class="editfield" data-k="cli_elink_id" value="<?php echo $row->cli_elink_id ?>" /></td>
                <td><input type="text" class="editfield" data-k="cli_elink_id2" value="<?php echo $row->cli_elink_id2 ?>" /></td>
                <td>
                    <select class="editfield" data-k="cli_Elink_lab_ID">
                        <?php
                        foreach ($labs as $lab) {
                            $selected = $lab->lab_ID == $row->cli_Elink_lab_ID ? 'selected' : '';
                            echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
        <?php
        }
        ?>
    </table>
<?php
    exit;
} else if ($act == 'save_elink') {
    $return = array('success' => 1, 'msg' => '');
    ob_clean();
    $id = $_REQUEST['id'];
    $col = trim($_REQUEST['col']);
    $vl = trim($_REQUEST['val']);

    $rs = $wpdb->update('ci_clinic', array($col => $vl), array('cID' => $id));

    if ($rs === false) {
        $return['success'] = 0;
        $return['msg'] = 'Error. Please try again';
    }
    wp_send_json($return);
} else if ($act == 'importfile') {
    if (isset($_FILES['file'])) {
        $msg = file_get_contents($_FILES['file']['tmp_name']);

        $rs = $elink->import_req_file(0, $msg);
        var_dump($rs);
        var_dump($elink->log);
    }
?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="file" />
        <input type="submit" />
    </form>
<?php
}


$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$doctors = $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        cd.cli_ID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            order by doc.doc_first asc", ARRAY_A);
?>


<div class="wrap">
    <h2>ELINK Settings</h2>

    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <div class="row ">
            <label>Active</label>
            <input type="checkbox" name="options[active]" value="1" <?php echo checked(isset($options['active']) ? $options['active'] : '') ?>" />
        </div>


        <div class="row">
            <label>&nbsp;</label>
            <input type="hidden" name="options[ma_msh4]" value="0" />
            <label style="width:auto"><input type="checkbox" name="options[ma_msh4]" value="1" <?php checked(isset($options['ma_msh4']) ? $options['ma_msh4'] : '') ?> /> Only match MSH6</label>

        </div>

        <div class="row">
            <label>&nbsp;</label>
            <input type="hidden" name="options[ma_elinkid]" value="0" />
            <label style="width:auto"><input type="checkbox" id="ma_elinkid_cb" name="options[ma_elinkid]" value="1" <?php checked(isset($options['ma_elinkid']) ? $options['ma_elinkid'] : '') ?> /> Only match ElinkID(MSH4)</label>

        </div>

        <div class="row ">
            <label>API Key</label>
            <input type="text" name="options[key]" value="<?php echo isset($options['key']) ? $options['key'] : '' ?>" maxlength="50" required style="width:50%" />
        </div>

        <div class="row ">
            <label>API port</label>
            <input type="number" name="options[port]" value="<?php echo isset($options['port']) ? $options['port'] : '' ?>" maxlength="50" required style="width:50%" />
        </div>

        <div style="border:1px dashed #ddd;margin:10px">
            <div class="row ">
                <label>MSH6</label>
                <input type="text" name="options[msh4]" value="<?php echo isset($options['msh4']) ? $options['msh4'] : '' ?>" maxlength="50" required style="width:50%" />
            </div>

            <div class="row">
                <label>Lab</label>
                <select name="options[lab_id]" required>
                    <?php
                    foreach ($labs as $lab) {
                        $selected = isset($options['lab_id']) && $lab->lab_ID == $options['lab_id'] ? 'selected' : '';
                        echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>


        <?php
        if (count($labs) > 1) {
            if (!isset($options['msh6list']) || !is_array($options['msh6list'])) {
                $options['msh6list'] = array();
            }
            if (!isset($options['lab_idlist']) || !is_array($options['lab_idlist'])) {
                $options['lab_idlist'] = array();
            }

            for ($im = 1; $im < count($labs); $im++) {
        ?>
                <div style="border:1px dashed #ddd;margin:10px">
                    <div class="row ">
                        <label>MSH6 #<?php echo $im + 1 ?></label>
                        <input type="text" name="options[msh6list][<?php echo $im ?>]" value="<?php echo isset($options['msh6list'][$im]) ? $options['msh6list'][$im] : '' ?>" maxlength="50"
                            required style="width:50%" />
                    </div>

                    <div class="row">
                        <label>Lab #<?php echo $im + 1 ?></label>
                        <select name="options[lab_idlist][<?php echo $im ?>]" required>
                            <?php
                            foreach ($labs as $lab) {
                                $selected = isset($options['lab_idlist'][$im]) && $lab->lab_ID == $options['lab_idlist'][$im] ? 'selected' : '';
                                echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
        <?php
            }
        }
        ?>



        <div class="row" id="clinic_div">
            <label>Clinic</label>
            <select name="options[cli_id]">
                <option value="">In file</option>
                <?php
                foreach ($clinics as $clinic) {
                    $selected = isset($options['cli_id']) && $clinic->cID == $options['cli_id'] ? 'selected' : '';
                    echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="row" id="doctor_div">
            <label>Doctor</label>
            <select name="options[doc_id]" data-value="<?php echo isset($options['doc_id']) ? $options['doc_id'] : '' ?>"></select>
        </div>

        <div class="row">
            <label>Schedule</label>
            <select name="options[cron]">
                <?php
                foreach (array(1, 2, 5, 10, 30) as $m) {
                    $selected = $m == $options['cron'] ? 'selected' : '';
                    echo '<option value="' . $m . '" ' . $selected . '>each ' . $m . ' min(s)</option>';
                }
                ?>
            </select>
        </div>

        <div class="frow">
            <input type="submit" class="button-primary" value="Submit">
            <?php
            if (!empty($options['key'])) {
            ?>
                <?php if (!$options['active']) { ?>
                    <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'test', 'trajax' => 1)) ?>">Test get Orders</a>
                    <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'test_send_rs', 'trajax' => 1)) ?>">Test send Result</a>
                <?php } ?>
                <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'sync', 'trajax' => 1)) ?>">Sync Reqs</a>
                <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'sync_reports', 'trajax' => 1)) ?>">Sync Reports</a>
                <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_elinkid', 'trajax' => 1)) ?>">List ElinkID</a>
                <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
                                                    if (isset($_GET['debug'])) echo '&debug=1';
                                                    if (isset($_GET['fix_code'])) echo '&fix_code=1' ?>&width=1000">Log</a>
                <a class="button" target="_blank" href="<?php echo '/wp-content/log/log_api_elink.txt' ?>">Log File</a>
            <?php
                $today = date_i18n('Y-m-d');
                $where = '';

                if (!empty($archive_date)) {
                    $where .= " and ba.doc >' {$archive_date}' ";
                }

                $count = $wpdb->get_row("select count(*) as count_reqs,
                        SUM(IF( (select count(*) from ci_reports_pcr re where re.patient_id = ba.assen and re.estatus='C')>0,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_batches_pcr ba
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
                            where o.mo_file_id='{$elink->api_id}' 
                            {$where}
                            ");



                echo '<br>';
                echo 'Total Reqs: ' . $count->count_reqs . '<br>';
                echo 'Total Results: ' . $count->count_results . '<br>';
                echo 'Total Sent: ' . $count->count_sent . '<br>';

                echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

                $next_cron = wp_next_scheduled('cron_api_elink');
                $current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;


                $last_cron = @filemtime(WP_CONTENT_DIR . '/log/log_api_elink.txt');
                if ($last_cron >  $next_cron - 60 * $options['cron']) {
                    $last_cron = $next_cron - 60 * $options['cron'];
                }
                if ($last_cron) {
                    echo 'Last Cron: ' . date('Y-m-d H:i:s', $last_cron + $current_offset) . '<br>';
                }

                if ($next_cron) {
                    echo 'Next Cron: ' . date('Y-m-d H:i:s', $next_cron + $current_offset) . '<br>';
                } else if ($options['active']) {
                    if (empty($options['cron'])) {
                        $options['cron'] = 2;
                    }
                    wp_clear_scheduled_hook('cron_api_elink');
                    wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_api_elink');
                }
            }
            ?>
        </div>
    </form>
</div>
<style>
    #elinktbl input.editfield {
        width: 100%;
    }
</style>
<script>
    var $doctors = <?php echo json_encode($doctors) ?>;
    var $selected_doc = <?php echo intval(isset($options['doc_id']) ? $options['doc_id'] : 0) ?>;
    jQuery(function($) {

        function save_elink_data($this) {
            var $pr = $this.parent();
            var $tr = $pr.parent();
            var $vl = $this.val();
            var $col = $this.data('k');
            var $id = $tr.data('id');

            $pr.append('<div class="sloading"></div>');
            $.ajax({
                data: {
                    act: 'save_elink',
                    trajax: 1,
                    col: $col,
                    id: $id,
                    val: $vl
                },
                type: 'post',
                dataType: 'json',
                success: function(rs) {
                    if (rs.success) {

                    } else {
                        alert(rs.msg);
                    }
                    $('.sloading', $pr).remove();
                }
            });
        }


        $('[name="options[cli_id]"]').change(function() {
            var $cli_id = $(this).val();
            var $html = '<option value="">Select Doctor</option>';
            for (i in $doctors) {
                var $doctor = $doctors[i];
                if ($doctor.cli_ID == $cli_id) {
                    var $selected = $doctor.doc_ID == $selected_doc ? 'selected' : '';
                    $html += '<option value="' + $doctor.doc_ID + '" ' + $selected + '>' + $doctor.doc_first + ' ' + $doctor.doc_last + '</option>';
                }
            }
            $('[name="options[doc_id]"]').html($html);


        }).change();
        $('body').on('click', 'a.show_popup', function() {
                if ($(this).hasClass('seach_assen') || $(this).hasClass('resend')) return;
                var $url = $(this).attr('href');
                //tb_remove();
                setTimeout(function() {
                    tb_show('', $url);
                }, 300);
                return false;
            })
            .on('click', 'a.resend', function() {
                if (!confirm('Do you want send?')) return false;
                if ($(this).hasClass('sending')) return false;
                $(this).addClass('sending');
                var $this = $(this);
                var $pr = $(this).parents('tr');
                var $id = $pr.data('id');
                $.ajax({
                    data: {
                        trajax: 1,
                        act: 'send_result',
                        id: $id
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function($rs) {
                        $this.removeClass('sending');
                        if ($rs.status == 'ok') {
                            alert('Sent successfully');
                        } else {
                            alert($rs.msg);
                        }
                    }
                });
                return false;
            })
            .on('click', 'a.seach_assen', function() {
                var $s = $('#assen_search').val();
                var $url = $(this).attr('href');
                setTimeout(function() {
                    tb_show('', $url + '&s=' + $s);
                }, 300);
                return false;
            })
            .on('change', '#ma_elinkid_cb', function() {
                if ($(this).is(':checked')) {
                    $('#clinic_div').hide();
                    $('#doctor_div').hide();
                } else {
                    $('#clinic_div').show();
                    $('#doctor_div').show();
                }
            })
            .on('keypress', '#elinktbl input.editfield', function(e) {
                var code = e.keyCode || e.which;
                if (code == 13) {
                    save_elink_data($(this));
                    return false;
                }
            })
            .on('change', '#elinktbl .editfield', function() {
                save_elink_data($(this));
            })

        $('#ma_elinkid_cb').change();
    })
</script>
