<?php
global $wpdb;
global $ci_all_btypes;


$start_time             = microtime(true);
$receiving_w3d          = tr_get_option('ci_pdf_data', 'receiving_w3d');
$receiving_w3dnum       = tr_get_option('ci_pdf_data', 'receiving_w3dnum');
if (empty($receiving_w3dnum)) {
    $receiving_w3dnum = 3;
}

$num_length             = tr_get_option('ci_pdf_data', 'barcode_length');
$receiving_show_panel   = true;
$receiving_time_bg      = tr_get_option('ci_pdf_data', 'receiving_time_bg');
$receiving_priority_bg  = tr_get_option('ci_pdf_data', 'receiving_priority_bg');
$enable_stat            = tr_get_option('ci_pdf_data', 'enable_stat');
$enable_stat_alert      = tr_get_option('ci_pdf_data', 'receiving_stat_alert');
$main_slug              = tr_get_option('ci_pdf_data', 'barcode_key');
$receiving_saved        = tr_get_option('ci_pdf_data', 'receiving_saved');
$show_doctor            = true;


if (in_array('req', $ci_all_btypes)) {
    $show_doctor        = false;
}

$num_length = $num_length > 5 ? $num_length : 8;

$return = array(
    'status' => 'notfound',
    're_ID' => 0,
    'print' => false,
    'created_receiving' => false,
    'stat_alert' => false,
);

$subtype        = '';
$today          = date_i18n('Y-m-d');
$datetime_now   = date_i18n('Y-m-d H:i:s');
$user_id        = get_current_user_id();


$base_positions = trpdd_get_receiving_base_positions();
$key_types = array();
foreach ($base_positions as $k => $vl) {
    $key_types[] = $k;
}


$type_of_specimen   = '';
$code               = strtoupper(trim($_REQUEST['code']));
$form_type          = isset($_REQUEST['ftype']) ? trim($_REQUEST['ftype']) : '';
$btype              = trim($_REQUEST['btype']);
$lab_id             = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
$parentdata         = $_REQUEST['parentdata'];
$next_positions     = isset($_REQUEST['next_positions']) ? $_REQUEST['next_positions'] : array();
$pass_3days         = isset($_REQUEST['pass_3days']) && $_REQUEST['pass_3days'] == 'yes';
$pass_type          = isset($_REQUEST['pass_type']) ? $_REQUEST['pass_type'] : '';
$pass_value         = '';
if (!empty($pass_type)) {
    list($pass_type, $pass_value) = explode('|', $pass_type);
}

$exist_positions    = trim($_REQUEST['exist_positions'], ',');
$exist_positions    = explode(',', $exist_positions);

$code               = str_replace(array('+', ' '), '', $code);

if (strlen($code) > $num_length + 5) {
    $return['status'] = 'error';
    $return['message'] = 'Error. Barcode "' . $code . '" is very long!';
    $return['loadedtime'] = microtime(true) - $start_time;
    wp_send_json($return);
    exit;
}


if ($lab_id == 0 && $parentdata['re_lab_ID'] > 0) {
    $lab_id = $parentdata['re_lab_ID'];
}
if ($lab_id == 0) {
    $lab_id = CI_Base_Lab::get_current_lab_id();
}

$key_pos    = $btype;
if ($key_pos == 'tox' && !isset($base_positions[$key_pos])) {
    $key_pos = 'urine';
}
$base_type  = $base_positions[$key_pos];
$btype      = $base_type['btype'];
$type_of_specimen = $base_type['specimen'];
$initials1  = $base_type['subtype'];
if (strtolower($initials1) == strtolower($type_of_specimen)) {
    $initials1 = '';
}


$table      = tr_pdd_get_table_by_btype($btype);


$specimen_types = tr_pdd_get_type_of_specimens($btype);
unset($specimen_types['Other']);
$specimen_types = array_unique($specimen_types);


if ($btype == 'pcr') {
    $type_of_specimen = '';
} else if (empty($type_of_specimen)) {
    $type_of_specimen = @key($specimen_types);
}
if (empty($type_of_specimen)) {
    $type_of_specimen = '';
}
$id = $_REQUEST['id'];
$ii = $_REQUEST['ii'];

$position = 1;


//check and create re
if ($parentdata['re_today'] && empty($parentdata['re_ID'])) {
    unset($parentdata['re_ID']);
    unset($parentdata['undefined']);


    $all_rev = $wpdb->get_results("select * from ci_receiving 
                        where re_today='{$parentdata['re_today']}' and re_type = 0 ", ARRAY_A);

    $last_pos = array();
    foreach ($all_rev as $r) {
        foreach ($base_positions as $k => $arr) {
            $prev_field = "re_prev_pos_{$k}";
            $npos = $r[$prev_field];
            if (!isset($last_pos[$k])) $last_pos[$k] = 0;
            if ($npos >= $last_pos[$k]) {
                $last_pos[$k] = $npos + $r['re_qty_' . $k];
            }
        }
    }
    $start_positions = array();
    foreach ($base_positions as $k => $arr) {
        $prev_field = "re_prev_pos_{$k}";
        $pos = isset($last_pos[$k]) && $last_pos[$k] > $arr['position'] ? $last_pos[$k] : $arr['position'];

        if ($pos > $arr['position'] + 3000 || $pos < $arr['position'] || $parentdata['re_qty_' . $k] == 1) {
            $pos = $arr['position'];
        }

        $start_positions[$k] = $pos;

        if (in_array($k, $key_types)) {
            $parentdata[$prev_field] = $pos;
        }
    }

    $parentdata['re_author_id'] = get_current_user_id();
    $parentdata['re_created'] = $datetime_now;
    $parentdata['re_type'] = 0;
    if (empty($parentdata['re_today'])) {
        $parentdata['re_today'] = $today;
    }
    $wpdb->insert('ci_receiving', $parentdata);

    $return['last_error'] = $wpdb->last_error;
    $return['re_ID'] = $wpdb->insert_id;
    $return['created_receiving'] = true;

    $logdata = array();
    foreach ($parentdata as $k => $v) {
        if (stripos($k, 'qty') === false && stripos($k, 'prev') === false) {
            $logdata[$k] = $v;
        }
    }

    trpdd_log_user($user_id, 'receiving_add', $logdata);
} else {
    $rev = array();
    if ($parentdata['re_ID'] > 0) {
        $rev = $wpdb->get_row("select * from ci_receiving where re_ID = {$parentdata['re_ID']} ", ARRAY_A);
    }

    $start_positions = array();
    foreach ($base_positions as $k => $arr) {
        $prev_field = "re_prev_pos_{$k}";
        $start_positions[$k] = $rev[$prev_field] > $arr['position'] ? $rev[$prev_field] : $arr['position'];
    }
    $update_rev = array();
    foreach ($parentdata as $k => $vl) {
        if (stripos($k, 're_qty') !== false && $parentdata[$k] != $rev[$k]) {
            $update_rev[$k] = $vl;
        }
    }

    if (count($update_rev) > 0) {
        $wpdb->update('ci_receiving', $update_rev, array('re_ID' => $parentdata['re_ID']));
    }
    $return['re_ID'] = $parentdata['re_ID'];
}


if ($return['re_ID'] == 0) {
    $return['status'] = 'error';
    $return['message'] = 'Error when create new Receiving!';
    $return['loadedtime'] = microtime(true) - $start_time;
    wp_send_json($return);
    exit;
}

if (empty($next_positions)) {
    $next_positions = $start_positions;
} else {
    foreach ($next_positions as $k => $pos) {
        if ($parentdata['re_qty_' . $k] == 1 || $pos < 0) {
            $next_positions[$k] = $start_positions[$k];
        }
        if ($next_positions[$k] == 0) {
            $next_positions[$k] = $base_positions[$k]['position'];
        }
    }

    foreach ($base_positions as $k => $arr) {
        if (!isset($next_positions[$k])) {
            $next_positions[$k] = $arr['position'];
        }
    }
}

$or_barcode2 = '';
if ($btype == 'blood' && substr($code, 2, 1) == 'W') {
    $code_main = $main_slug . substr($code, 3);
    $or_barcode2 = " or assen = '{$code_main}' ";
    $subtype = 'W';
}
$batch = $wpdb->get_row("select * from {$table} where assen='{$code}' {$or_barcode2} limit 1");

//preg match all are number
if (!$batch && preg_match('/^\d+$/', $code) === 1) {
    $labinit = CI_Base_Lab::get_current_lab_init();
    $code = $labinit . $code;
    $batch = $wpdb->get_row("select * from {$table} where assen='{$code}' order by mid desc limit 1");
}
if (!$batch) {
    $batch = $wpdb->get_row("select * from {$table} where oldreq='{$code}' order by mid desc limit 1");
} else if (substr($batch->assen, 2, 1) == 'W') {
    $subtype = '';
}

$return['loadedtime1'] = microtime(true) - $start_time;

//check rejected 2025/09/26
$verified_jejected = false;
if ($batch && !empty($batch->batch_status)) {
    $rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, $btype);

    if (isset($rejected_statuses[$batch->batch_status])) {
        //$pass_type, $pass_value)

        if ($pass_type == 'rejected') {
            $verified_jejected = $pass_value == 'yes';
            if ($pass_value == 'no') {
                $return['status'] = 'none';
                $return['message'] = 'This sample ' . $code . ' was previously Rejected!';
                wp_send_json($return);
            }
        }

        if (!$verified_jejected) {
            $return['status'] = 'choose';
            $return['choose_type'] = 'rejected';
            $return['choose_return_no'] = true;
            $return['width'] = 450;
            $return['message'] = 'This sample ' . $code . ' was previously Rejected:<br>
            ' . $rejected_statuses[$batch->batch_status] . '<br>
            Do you want to Receive it?';
            wp_send_json($return);
        }
    }
}


//check for api
if (!$batch && $btype == 'pcr') {
    $barcode_key = tr_get_option('ci_pdf_data', 'barcode_key');
    if (stripos($code, $barcode_key) === false) {
        //search in sync api
        $mid = $wpdb->get_var("select mo_mid from ci_sync_api_orders where mo_test_ID = '{$code}' or mo_peg_ID = '{$code}'");
        if ($mid > 0) {
            $batch = $wpdb->get_row("select * from {$table} where mid = {$mid} ");
            $return['print'] = true;
            if ($batch) {
                $code = $batch->assen;
            }
        }
    }
}

//check Cynergy api
if (!empty($pass_type) && $pass_type == 'cynergy') {
    if ($pass_value == 'yes') {
        $rejected_status = $wpdb->get_var("select s_key from ci_batches_status where btype='req' and s_type = 're' and s_text like '%Rejected - Physician Authorization could not be obtaine%'");
        $rejected_status = 're_' . $rejected_status;
        $wpdb->update($table, array('batch_status' => $rejected_status), array('mid' => $batch->mid));
    }
} else if ($batch && $batch->paid == -47 && $btype == 'req') {
    $cynergy_enable = $wpdb->get_var("select count(*) from ci_wsdl_keys where wsdl_active =1 and wsdl_api_type=2");
    if ($cynergy_enable) {
        $verified = trpdd_get_batch_meta($batch->mid, $btype, '_cynergy_status');
        if (!$verified) {
            $return['status'] = 'choose';
            $return['choose_type'] = 'cynergy';
            $return['message'] = 'Physician Authorization Failed. Should we reject the sample accordingly?';
            wp_send_json($return);
        }
    }
}

if ($batch && $batch->cli_ID > 0) {
    //check exits
    $sql_duplicate = "select ID 
    from ci_receiving_batches 
    where mid = {$batch->mid} 
    and btype='{$btype}'";
    if (!empty($subtype)) {
        $sql_duplicate .= " and subtype = '{$subtype}' ";
    }
    $rb = $wpdb->get_var($sql_duplicate);
    if ($rb > 0) {
        $batch = null;
        $return['status'] = 'error';
        $return['existid'] = $rb;
        $return['message'] = 'This Barcode is duplicate';
        $return['loadedtime'] = microtime(true) - $start_time;
        wp_send_json($return);
    }
}


//check $receiving_w3d
if ($receiving_w3d && $batch && !$pass_3days && !$verified_jejected) {
    $doc_t = strtotime($batch->doc);
    $now_t = strtotime($datetime_now);

    if ($doc_t > 1000 && $doc_t < $now_t - (86400 * $receiving_w3dnum)) {
        $return['status'] = 'm3days';
        $return['existid'] = isset($rb) ? $rb : 0;
        $return['message'] = 'Sample is older than ' . $receiving_w3dnum . ' days!';
        $return['loadedtime'] = microtime(true) - $start_time;
        wp_send_json($return);
    }
}


//check duplicate other type
if (!$batch) {
    foreach (tr_pdd_get_batch_types() as $bb) {
        if ($bb == $btype || $bb == 'study') {
            continue;
        }
        $btable = tr_pdd_get_table_by_btype($bb);
        $exist = $wpdb->get_var("select mid from {$btable} where assen='{$code}' ");
        if ($exist > 0) {
            $return['status'] = 'error';
            $return['message'] = 'This Barcode is duplicate in other type';
            $return['loadedtime'] = microtime(true) - $start_time;
            wp_send_json($return);
            break;
        }
    }
}


if ($batch) {
    $return['status'] = 'ok';

    //update $type_of_specimen
    if (empty($batch->type_of_specimen) && !empty($type_of_specimen)) {
        $batch->type_of_specimen = $type_of_specimen;
    }
} else if (strlen($code) >= 3) {
    $return['status'] = 'ok';
    $lab_obj = $wpdb->get_row("select lab_dba_init, lab_is_ref
                            from ci_labs 
                            where lab_ID = {$lab_id} ");


    $lab_dba_init = $lab_obj->lab_dba_init;


    $bat = array(
        'batch_type' => 'M',
        'assen' => $code,
        'lab_id' => $lab_id,
        'lab' => $lab_dba_init,
        'batch_created' => $datetime_now,
        'batch_id' => '',
        'batch_status' => 'received',
        'spec_received' => $datetime_now,
        'type_of_specimen' => $type_of_specimen,
        'author_ID' => get_current_user_id(),
        'doc' => $datetime_now,
        'initials1' => $initials1
    );

    if ($receiving_saved) {
        $bat['is_saved'] = 1;
    }

    $rs = $wpdb->insert($table, $bat);
    if ($rs === false) {
        $return['status'] = 'error';
        $return['message'] = $wpdb->last_error;
    } else {
        //find and remove other type
        foreach (tr_pdd_get_batch_types() as $bt) {
            if ($bt != $btype) {
                $tablecheck = tr_pdd_get_table_by_btype($bt);
                $wpdb->query("delete from {$tablecheck} where assen = '{$code}' and batch_id = 0 and cli_ID=0");
            }
        }

        $barcode_key = substr($code, 0, 3);

        $syncref = $wpdb->get_row("select ws.* 
	                    from ci_wsdl_keys ws 
	                    JOIN ci_labs lab ON lab.lab_ID = ws.wsdl_ref_lab_ID
                            where ws.wsdl_active = 1 
                            and ws.wsdl_type = 'getreqrec'
                            and lab.lab_dba_init = '{$barcode_key}'
                            and (lab.lab_is_ref =1 or lab.lab_is_ref =2) ");

        if ($syncref) {
            try {
                $client = new CI_Data_Work_Client($syncref);
                $client->ref_lab_id = $syncref->wsdl_ref_lab_ID;
                $client->default_cli_ID = $syncref->wsdl_df_cli_ID;
                $client->default_doc_ID = $syncref->wsdl_df_doc_ID;

                $rs = $client->sync_req($code, $btype);
                // $return['rs1'] = $rs;
            } catch (Exception $e) {
            }
        }
    }
    $batch = $wpdb->get_row("select * from {$table} where assen='{$code}' ");
}


if ($batch) {
    $class = '';
    $moretext = '';
    $update = array();

    if ($batch->initials1 != $initials1) {
        $update['initials1'] = $batch->initials1 = $initials1;
    }

    if (!empty($batch->patient_name)) {
        $moretext .= $batch->patient_name;
    }
    $dob_time = strtotime($batch->dob);
    if (!empty($batch->dob) && $dob_time > 0) {
        $dob = date('m/d/Y', $dob_time);
        $moretext .= '  ' . $dob;
    }


    if (empty($batch->type_of_specimen) && in_array($btype, array('tox', 'blood'))) {
        $batch->type_of_specimen = $type_of_specimen;
        $update['type_of_specimen'] = $batch->type_of_specimen;
    }


    $mid_btype = $batch->mid;

    if ($btype != 'tox') {
        $mid_btype = $btype . '_' . $batch->mid . '_' . $subtype;
    }

    $batch->alcohol = stripos($batch->panel_tests_requested, 'etg,') !== false && stripos($batch->panel_tests_requested, ',ets') !== false;

    $next_positions[$key_pos]++;


    $sql_exist_positions = "select ba.position from {$table} ba
	                        where ba.mid in 
	                        (select rb.mid from ci_receiving_batches rb 
	                            join ci_receiving ri ON ri.re_ID = rb.re_ID
	                        where rb.btype = '{$btype}' 
	                                and ri.re_today = '{$parentdata['re_today']}' 
	                        )";
    $exist_positions2 = $wpdb->get_col($sql_exist_positions);

    if (count($exist_positions2) > 0) {
        $exist_positions = array_merge($exist_positions, $exist_positions2);
    } else {
        $return['last_error_pos'] = $wpdb->last_error;
    }

    $has_exist_pos = false;
    while (in_array($next_positions[$key_pos], $exist_positions)) {
        $next_positions[$key_pos]++;
        $has_exist_pos = true;
    }

    $position = $next_positions[$key_pos];

    if ($batch->position != $position) {
        $update['position'] = $batch->position = $position;
    }



    //update prev
    if ($return['created_receiving']) {
        $wpdb->update('ci_receiving', array('re_prev_pos_' . $key_pos => $position - 1), array('re_ID' => $return['re_ID']));
    }


    //add to receiving batch
    $rebat_ID = $wpdb->get_var("select ID from ci_receiving_batches 
    where re_ID = {$return['re_ID']} 
    and mid = {$batch->mid} 
    and btype='{$btype}' 
    and subtype = '{$subtype}' ");
    if ($rebat_ID == 0) {
        $wpdb->insert('ci_receiving_batches', array(
            're_ID' => $return['re_ID'],
            'mid' => $batch->mid,
            'btype' => $btype,
            'subtype' => $subtype,
            'created' => $datetime_now,
            'created_by' => $user_id,
        ));
        $rebat_ID = $wpdb->insert_id;

        $old_status = $wpdb->get_var("select batch_status from {$table} where mid = {$batch->mid} and cli_ID = 0");
        if ($old_status != 'received') {
            $update['batch_status'] = 'received';
            $update['spec_received'] = $datetime_now;
        }

        trpdd_log_user($user_id, 'receiving', $batch->assen, $batch->mid, $btype);
    }

    $lab_obj = $wpdb->get_row("select lab_dba_init, lab_is_ref
                            from ci_labs 
                            where lab_ID = {$lab_id} ");

    if ($lab_obj && $lab_obj->lab_is_ref > 0) {
        if ($batch->ref_lab_ID == 0 && $batch->ref_lab_ID != $lab_id && $batch->lab_id != $lab_id) {
            $update['ref_lab_ID'] = $lab_id;
            trpdd_log_user($user_id, 'rev_reflab', 'Changed ref lab ', $batch->mid, $btype);
        }
    }




    if ($rebat_ID == 0) {
        $class .= 'childp_new';
    }

    $class .= 'assen_' . $batch->assen;

    if (!empty($batch->dob)) {
        $batch->dob = tr_pdd_display_date($batch->dob);
    }

    $specimen_selecteds = !empty($batch->type_of_specimen) ? explode(',', $batch->type_of_specimen) : array();

    $panels_ordered = array();
    $panels_print = array();

    if (in_array($btype, array('pcr', 'blood'))) {
        $group_ids          = array();
        $panels_specimens   = array();
        $has_change_spec    = false;

        foreach (explode(',', $batch->panel_tests_requested) as $t) {
            if (stripos($t, 'GROUP_') !== false) {
                $group_id = str_replace('GROUP_', '', $t);
                $group_ids[] = $group_id;


                if ($btype == 'pcr') {
                    $spbgrs = $wpdb->get_col("select ts_name from ci_test_specimens where ts_group_id={$group_id} and ts_type='{$btype}'");

                    foreach ($spbgrs as $spec) {
                        $spec = trim($spec);
                        if (!empty($spec)) {
                            $panels_specimens[$spec] = $spec;
                            if (count($spbgrs) == 1 && !in_array($spec, $specimen_selecteds)) {
                                $specimen_selecteds[] = $spec;
                                $has_change_spec = true;
                            }
                        }
                    }
                }
            }
        }
        if (count($group_ids) > 0) {
            $group_ids_str = implode(',', $group_ids);
            $panels_ordered = $wpdb->get_col("select tg_name from ci_test_groups where tg_ID in ({$group_ids_str})");

            if ($btype == 'pcr') {
                $specimen_types = $panels_specimens;
                if ($has_change_spec) {
                    $specimen_selecteds     = array_unique($specimen_selecteds);
                    $update['type_of_specimen'] = implode(',', $specimen_selecteds);
                }
            }
        }
    }

    if (count($update) > 0) {
        $wpdb->update($table, $update, array('mid' => $batch->mid));
    }


    $panels_print_str = implode('|', $panels_print);
    if ($btype == 'blood') {
        $panels_print_str = '';
    }

    ob_start();
    $hourcolor = '';
    if ($receiving_time_bg) {
        $hour = date('H', strtotime($batch->doc));
        if ($hour >= 8 && $hour <= 12) {
            $hourcolor = 'bg_red';
        } else if ($hour > 12 && $hour <= 16) {
            $hourcolor = 'bg_orange';
        } else if ($hour > 16 && $hour <= 20) {
            $hourcolor = 'bg_blue';
        }
    }

    if ($enable_stat && $batch->stat == 1) {
        $return['stat_alert'] = 'This sample is STAT!';
    }

    $assen = $batch->assen;
    $sub2 = substr($assen, 0, 2);
    if (stripos($code, $sub2) === 0) {
        $assen = $code;
    }
?>
    <tr class="childp <?php echo $class ?>" data-key_pos="<?php echo $key_pos ?>" data-panels_print="<?php echo $panels_print_str ?>" data-dob="<?php echo $batch->dob ?>" data-mid="<?php echo $mid_btype ?>" data-spec_type="<?php echo $batch->type_of_specimen ?>" data-btype="<?php echo $btype ?>" data-pos="<?php echo $position ?>">
        <td><?php echo $ii ?>
            <input type="hidden" class="mid" value="<?php echo $mid_btype ?>" />
        </td>
        <td class="col-position"><input type="text" data-key="<?php echo $key_pos ?>" value="<?php echo $position ?>" /></td>
        <td class="assen"><?php echo $assen ?></td>
        <td class="<?php echo $hourcolor ?> doc">
            <?php
            if (!$show_doctor) {
                echo '<input type="text" class="hasdatetime" value="' . $batch->doc . '"/>';
            } else {
                echo tr_pdd_display_date($batch->doc, 'Y-m-d H:i');
            }

            ?>
        </td>
        <td class="patient_name"><?php echo $batch->patient_name ?></td>
        <?php
        if (tr_get_option('ci_pdf_data', 'receiving_sdob')) {
            echo '<td class="dob">' . $batch->dob . '</td>';
        }
        if ($form_type != 'plate') {
        ?>
            <td><?php echo $batch->requesting_practice ?></td>
            <?php if ($show_doctor) { ?>
                <td><?php echo $batch->requesting_physician ?></td>
            <?php } ?>
            <?php if ($receiving_show_panel) { ?>
                <td><?php echo implode(',<br>', $panels_ordered) ?></td>
            <?php } ?>
            <td class="spec_type">
                <?php
                $specimen_cbtype = 'radio';
                if ($btype == 'pcr') {
                    $specimen_cbtype = 'checkbox';
                } else if ($btype == 'blood') { //&& tr_get_option('ci_req_settings_blood', 'specimen_checkbox')
                    $specimen_cbtype = 'checkbox';
                }
                foreach ((array)$specimen_types as $k => $vl) {
                ?>
                    <div>
                        <label><input type="<?php echo $specimen_cbtype ?>" class="spec_type" name="spec_type[<?php echo $mid_btype ?>]" value="<?php echo $k ?>" <?php checked(in_array($k, $specimen_selecteds)) ?> /> <?php echo $vl ?> </label>
                    </div>
                <?php
                }
                if (!empty($batch->initials1)) {
                    echo ' <span class="small">' . $batch->initials1 . '</span>';
                }
                ?>
            </td>
            <?php if ($receiving_priority_bg) {
                $background_priority = '';
                if ($batch->priority_hours == '-1') {
                    $background_priority = 'bg_green';
                } else if ($batch->priority_hours == '-2') {
                    $background_priority = 'bg_red';
                    if ($enable_stat_alert) {
                        $return['stat_alert'] = 'This sample is STAT!';
                    }
                }
            ?>
                <td type="Priority" class="<?php echo $background_priority ?>"></td>
            <?php } ?>
            <td>
                <?php if ($btype == 'tox') {
                ?>
                    <label><input type="checkbox" class="alcohol" value="1" <?php checked($batch->alcohol) ?> />
                        Alcohol</label>
                    <?php
                } else if ($btype == 'pcr') {
                    $pcr_settings           = get_option('ci_req_settings_pcr', array());
                    if (isset($pcr_settings['enabel_cultureid']) && $pcr_settings['enabel_cultureid']) {
                    ?>
                        <label><input type="checkbox" class="culture" value="1" <?php checked($batch->culture) ?> />
                            Culture</label>
                <?php
                    }
                }
                ?>
            </td>
            <td><a class="print_barcode button" href="#">Print</a></td>
        <?php
        }
        ?>
        <td><a class="button remove_item">-</a> </td>
    </tr>
<?php

    $return['moretext'] = urlencode($moretext);
    $return['html'] = ob_get_clean();
    $return['position'] = $position;
    $return['next_positions'] = $next_positions;
    $return['key_pos'] = $key_pos;
    //$return['exist_positions'] = $exist_positions;
} else {
    $return['status'] = 'error';
    $return['message'] = 'Not found';
}

$return['loadedtime'] = microtime(true) - $start_time;

wp_send_json($return);
