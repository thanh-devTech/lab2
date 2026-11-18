<?php
global $wpdb, $current_role;

$datetime_now = date_i18n('Y-m-d H:i:s');
$today = date_i18n('Y-m-d');

if (isset($_REQUEST['today'])) {
    $today = $_REQUEST['today'];
}

$batch_tbl      = 'ci_batches_study';
$btype          = 'study';
$re_type        = 0;
$receiving_tbl  = 'ci_receiving_study';
$re_batch_tbl   = 'ci_receiving_study_batches';

if (isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'receiving_form2') {
    $re_type = 1;
}

$user_id = get_current_user_id();

function tr_study_receiving_row($rowdata)
{
    global $wpdb, $ci_all_studies;

    if (empty($ci_all_studies)) {
        $ci_all_studies = array();

        $rs    = $wpdb->get_results('select * from ci_study where istatus=1 order by project_name asc');
        foreach ($rs as $r) {
            $ci_all_studies[$r->ID] = $r;
        }
    }
    $class      = 'assen_' . $rowdata->code;
    $position   = $rowdata->position;

    $assigned_studies = !empty($rowdata->study_assigned) ?  explode(',', $rowdata->study_assigned) : array();
    $assigned_names = array();
    $assigned_qty_edta = array();
    $assigned_qty_citrate = array();
    $assigned_qty_sst_tube = array();
    foreach ($assigned_studies as $sid) {
        if (isset($ci_all_studies[$sid])) {
            $study = $ci_all_studies[$sid];
            $assigned_names[] = $study->project_name;
            $assigned_qty_edta[] = $study->num_ml_edta;
            $assigned_qty_citrate[] = $study->num_ml_citrate;
            $assigned_qty_sst_tube[] = $study->num_sst_tube;
        }
    }
    $slides = json_decode($rowdata->slides, true);
    $rowdata->qty_slides = count($slides);
?>
    <tr class="childp openpr <?php echo $class ?>" data-id="<?php echo $rowdata->ID ?>" data-pos="<?php echo $position ?>">
        <td><i class="openc"></i></td>
        <td><?php echo $rowdata->number + 1 ?>
        </td>
        <td class="col-position"><input type="text" value="<?php echo $position ?>" /></td>
        <td class="assen"><?php echo $rowdata->code ?></td>
        <td class=" doc"><?php echo tr_pdd_display_date($rowdata->doc, 'Y-m-d H:i') ?></td>
        <td></td>
        <td class="study_names"><?php echo implode('<br>', $assigned_names) ?></td>
        <td class="center study_edta"><?php echo implode('<br>', $assigned_qty_edta) ?></td>
        <td class="center study_citrate"><?php echo implode('<br>', $assigned_qty_citrate) ?></td>
        <td class="center study_sst_tube"><?php echo implode('<br>', $assigned_qty_sst_tube) ?></td>
        <td class="center qty_slides"><?php echo $rowdata->qty_slides ?></td>
        <td><a class="print_barcode button" href="#">Print</a></td>
        <td><a class="button remove_item">-</a> </td>
    </tr>
<?php
}


if ($act == 'receiving_req_form') {

    $return = array('status' => 'ok', 'html' => '');
    ob_start();
    include(trpdd_get_file_path('study', 'receiving_req_form'));
    $return['html'] = ob_get_clean();
    wp_send_json($return);
} else if ($act == 'search_barcode_receiving') {


    $start_time             = microtime(true);
    $num_length = $num_length > 5 ? $num_length : 8;

    $return = array(
        'status' => 'notfound',
        're_ID' => 0,
        'print' => false,
        'created_receiving' => false,
        'stat_alert' => false,
    );

    $today          = date_i18n('Y-m-d');
    $datetime_now   = date_i18n('Y-m-d H:i:s');
    $user_id        = get_current_user_id();


    $type_of_specimen   = '';
    $code               = trim($_REQUEST['code']);
    $lab_id             = $_REQUEST['lab_id'];
    $parentdata         = $_REQUEST['parentdata'];

    $exist_positions    = trim($_REQUEST['exist_positions'], ',');
    $exist_positions    = explode(',', $exist_positions);

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


    $id = $_REQUEST['id'];
    $ii = $_REQUEST['ii'];

    $position = 1;

    $parentdata['re_type'] = $re_type;
    //check and create re
    if ($parentdata['re_today'] && empty($parentdata['re_ID'])) {
        unset($parentdata['re_ID']);
        unset($parentdata['undefined']);


        $all_rev = $wpdb->get_results("select * from {$receiving_tbl} 
                            where re_type = {$re_type} and re_today='{$parentdata['re_today']}' ", ARRAY_A);



        $parentdata['re_author_id'] = get_current_user_id();
        $parentdata['re_created'] = $datetime_now;
        if (empty($parentdata['re_today'])) {
            $parentdata['re_today'] = $today;
        }
        $wpdb->insert($receiving_tbl, $parentdata);

        $return['last_error'] = $wpdb->last_error;
        $return['re_ID'] = $wpdb->insert_id;
        $return['created_receiving'] = true;
    } else {
        $rev = array();
        if ($parentdata['re_ID'] > 0) {
            $rev = $wpdb->get_row("select * from {$receiving_tbl} where re_ID = {$parentdata['re_ID']} ", ARRAY_A);
        }

        $update_rev = array();
        foreach ($parentdata as $k => $vl) {
            if (stripos($k, 're_qty') !== false && $parentdata[$k] != $rev[$k]) {
                $update_rev[$k] = $vl;
            }
        }

        if (count($update_rev) > 0) {
            $wpdb->update($receiving_tbl, $update_rev, array('re_ID' => $parentdata['re_ID']));
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

    //check exist
    $exist = $wpdb->get_var("select ID from {$re_batch_tbl} rb
    where re_ID = {$return['re_ID']} and code = '{$code}'");

    if (!$exist) {
        // $exist = $wpdb->get_var("select mid from ci_batches_study rb
        // where oldreq = '{$code}'");
    }



    if ($exist) {
        $return['status'] = 'error';
        $return['message'] = 'Duplicate code: ' . $code;
        $return['loadedtime'] = microtime(true) - $start_time;
        wp_send_json($return);
    } else {
        $return['status'] = 'ok';
        $class = '';

        $next_position++;

        $exist_positions2 = $wpdb->get_col("select position from {$re_batch_tbl} rb
        where re_ID = {$return['re_ID']} ");

        if (count($exist_positions2) > 0) {
            $exist_positions = array_merge($exist_positions, $exist_positions2);
        } else {
            $return['last_error_pos'] = $wpdb->last_error;
        }

        $has_exist_pos = false;
        while (in_array($next_position, $exist_positions)) {
            $next_position++;
            $has_exist_pos = true;
        }

        $position = $next_position;


        //add to receiving batch

        $rowdata = array(
            're_ID' => $return['re_ID'],
            'code' => $code,
            'created' => $datetime_now,
            'created_by' => $user_id,
            'position' => $position,
        );
        $rsq  = $wpdb->insert($re_batch_tbl, $rowdata);

        if ($rsq === false) {
            $return['errorinsertbdata'] = $rowdata;
            $return['errorinsertb'] = $wpdb->last_error;
        }


        $rebat_ID = $wpdb->insert_id;

        ob_start();

        $rowdata = (object) $rowdata;

        $rowdata->number = $ii;
        $rowdata->ID = $rebat_ID;
        tr_study_receiving_row($rowdata);

        $return['html'] = ob_get_clean();
        $return['position'] = $position;
    }

    $return['loadedtime'] = microtime(true) - $start_time;

    wp_send_json($return);
} else if ($act == 'remove_receiving') {
    $return = array('status' => 'ok');
    $id     = $_REQUEST['ID'];
    $re_ID  = $_REQUEST['re_ID'];

    $wpdb->query("delete from {$re_batch_tbl} where re_ID = {$re_ID} and ID = {$id}  ");

    wp_send_json($return);
} else if ($act == 'ci_update_position') {
    $return = array('status' => 'ok');
    $id = $_REQUEST['ID'];
    $position = $_REQUEST['position'];
    $re_ID = $_REQUEST['re_ID'] > 0 ? $_REQUEST['re_ID'] : 0;

    if ($re_ID > 0) {
        $receiving = $wpdb->get_row("select * from {$receiving_tbl} where re_ID = '{$re_ID}' ");
    }
    $wpdb->update($re_batch_tbl, array('position' => $position), array('ID' => $id));

    wp_send_json($return);
} else if ($act == 'ci_update_doc') {
    $return = array('status' => 'ok');
    $update = array();

    $mid     = $_REQUEST['mid'];
    $doc    = $_REQUEST['doc'];
    $re_ID     = $_REQUEST['re_ID'];


    $update = array('doc' => $doc);
    $rs = $wpdb->update('ci_batches_study', $update, array('mid' => $mid));

    if ($rs === false) {
        $return['status'] = 'error';
        $return['msg'] = $wpdb->last_error;
    }


    wp_send_json($return);
} else if ($act == 'ci_update_receiving') {
    $return = array('status' => 'ok');

    $re_ID = $_REQUEST['re_ID'];
    $fieldname = $_REQUEST['fieldname'];
    $val = $_REQUEST['val'];

    if ($re_ID > 0) {
        $receiving = $wpdb->get_row("select * from {$receiving_tbl} where re_ID = {$re_ID} ");
        $rs = $wpdb->update($receiving_tbl, array($fieldname => $val), array('re_ID' => $re_ID));

        if ($rs === false) {
            $return['status'] = 'error';
            $return['msg'] = $wpdb->last_error;
        }
    } else {
        $return['status'] = 'error';
        $return['msg'] = 'empty ID';
    }

    wp_send_json($return);
} else if ($act == 'autocode') {
    $return = array('status' => 'ok');
    $type = $_REQUEST['type'];
    $precode = $type == 2 ? 'TX' : 'RC';
    if ($type == 3) {
        $precode = 'BV';
    } else if ($type == 4) {
        $precode = 'SC';
    }
    $num_length = 9;
    $autonumber = get_option('ci_auto_code_rstudy_' . $type, 0) + 1;
    update_option('ci_auto_code_rstudy_' . $type, $autonumber, false);
    $return['code'] = $precode . sprintf('%0' . $num_length . 's', $autonumber);
    wp_send_json($return);
}

add_thickbox();
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('wp-jquery-ui-dialog');

$created = date_i18n('Y-m-d H:i:s');

$priorities = array('low' => 'Low', 'med' => 'Med', 'high' => 'High');
$timings = array('24' => '24hrs', '48' => '48hrs', '72' => '72hrs', '120' => '5days', '168' => '7days');


$current_time = time() + (get_option('gmt_offset') * HOUR_IN_SECONDS);
$today = date('Y-m-d', $current_time);

if ($act == 'delete' && $_REQUEST['id'] > 0) {
    $wpdb->delete($receiving_tbl, array('re_ID' => $_REQUEST['id']));
    Tr_Session_Class::add('deleted successfully!');
}

$tabs = array(
    'receiving_form' => ' Blood Receiving',
    'receiving_form2' => 'Frozen and Slides Receiving',
    // 'list_receiving' => 'List',
);

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($tabs[$tab])) {
    $tab = key($tabs);
}
if (!isset($_REQUEST['trajax'])) {
?>
    <div class="wrap">
        <h2 class="nav-tab-wrapper">
            <?php

            foreach ($tabs as $k => $txt) {
                $class = $tab == $k ? 'nav-tab-active' : '';
                $link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'date' => '', 'assen' => ''), false);
                echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
            }
            ?>
        </h2>
    </div>
<?php
}


include(trpdd_get_file_path('study', $tab));
