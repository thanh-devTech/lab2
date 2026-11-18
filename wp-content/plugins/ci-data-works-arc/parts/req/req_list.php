<?php

global $wpdb;
global $current_role;
global $blog_id;


$settings       = get_option('ci_req_settings_req', array());

$btype      = 'req';
$batch_tbl = tr_pdd_get_table_by_btype($btype);

if ($act == 'single_change_hold') {
    $return = array('status' => 'ok');
    $mid        = $_REQUEST['mid'];
    $reason     = $_REQUEST['reason'];
    $reason_title     = isset($_REQUEST['reason_title']) ? $_REQUEST['reason_title'] : '';

    $batch = $wpdb->get_row("select * from {$batch_tbl} where mid = {$mid}");
    if ($batch->batch_status != 'hold') {
        $wpdb->update($batch_tbl, array('batch_status' => 'hold'), array('mid' => $mid));
        $wpdb->replace($batch_tbl . '_meta', [
            'mid' => $mid,
            'meta_key' => 'hold_batch_status',
            'meta_value' => $batch->batch_status
        ]);

        trpdd_log_user(get_current_user_id(), 'hold_rs', array(
            'reason' => $reason,
            'reason_title' => $reason_title
        ), $mid, $btype, '', '', $batch['assen']);
    }
    wp_send_json($return);
} else if ($act == 'single_remove_hold') {
    $return     = array('status' => 'ok');
    $mid        = $_REQUEST['mid'];
    $reason     = '';

    $batch = $wpdb->get_row("select * from {$batch_tbl} where mid = {$mid}");
    if ($batch->batch_status == 'hold') {
        $hold_status = $wpdb->get_var("select meta_value from {$batch_tbl}_meta where mid={$mid} and meta_key = 'hold_batch_status'") ?: '';
        $wpdb->update($batch_tbl, array('batch_status' => $hold_status), array('mid' => $mid));
        $wpdb->delete($batch_tbl . '_meta', ['mid' => $mid, 'meta_key' => 'hold_batch_status']);
        trpdd_log_user(get_current_user_id(), 'removehold_rs', $reason, $mid, $btype, '', '', $batch['assen']);
    }
    wp_send_json($return);
} else if ($act == 'change_qc') {
    $return = array('status' => 'ok', 'msg' => 'Updated');
    $table = 'ci_batches_req';
    $mid = $_REQUEST['mid'];
    $checked = $_REQUEST['checked'] ? 1 : 0;
    $wpdb->update($table, array('fasting' => $checked), array('mid' => $mid));

    wp_send_json($return);
} else if ($act == 'delete' && $_GET['id'] > 0) {
    trpdd_delete_batch($_GET['id'], 'req');
    Tr_Session_Class::add('Deleted Req record');
}

$today          = date('Y-m-d', time() + (get_option('gmt_offset') * HOUR_IN_SECONDS));
$edit_time      = strtotime($today);
$edit_time      -= 86400 * 30;
$can_edit       = true;
$can_delete     = false;

$user_id        = get_current_user_id();
$cls_id         = array();



$all_tests = array();
$all_groups = array();
$ts = $wpdb->get_results("select 
te_ID, 
te_name, 
te_label_barcode,
te_type_id
from ci_test_panels
where te_type in ('blood','pcr') ");
foreach ($ts as $t) {

    $t->is_w = false;
    if (isset($req_settings['print_secondlb']) && $req_settings['print_secondlb'] > 0) {
        if ($req_settings['print_secondlb'] == $t->te_type_id & stripos($t->te_name, 'A1C') !== false) {
            $t->is_w = true;
        }
    }

    $all_tests[$t->te_ID] = $t;
}


$ts = $wpdb->get_results("select tg_ID, tg_name, tg_label_name from ci_test_groups where tg_type = 'req'");
foreach ($ts as $t) {
    $all_groups[$t->tg_ID] = $t;
}



$conditions = trpdd_get_batch_conditions(0, 'req');

$where      = ' is_saved = 0 ' . $conditions['where'];

if ($conditions['check_author']) {
    $where      .= $return['where_author'];
}

$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];

if (!empty($_REQUEST['s'])) {
    $search = stripslashes($_REQUEST['s']);

    if (strpos($_REQUEST['s'], ',') > 0) {
        list($firstname, $lastname) = explode(',', $_REQUEST['s']);
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        $explodeString = "%{$firstname}%{$lastname}%";
        $where .= $wpdb->prepare(" and (ba.patient_name like %s)", $explodeString);
    } else {
        $where .= $wpdb->prepare(
            " and (ba.patient_name like %s 
                    or ba.assen like %s
                    or ba.oldreq = %s)",
            '%' . $search . '%',
            $search . '%',
            $search
        );
    }
}

$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_req ba
        left join ci_clinic cli ON cli.cID = ba.cli_ID where  {$where}"));
$offset = $pagination->offset();

$sql = "select
        ba.assen,
        ba.mid,
        ba.position,
        ba.doc,
        ba.batch_created,
        ba.fasting,
        ba.requesting_physician,
        ba.panel_tests_requested,
        ba.dob,
        ba.culture,
        ba.patient_name,
        ba.batch_status,
        ba.type_of_specimen,
        cli.cli_clinic
        from ci_batches_req ba
        left join ci_clinic cli ON cli.cID = ba.cli_ID
        where {$where} order by doc desc limit {$offset},{$row_per_page}";

if (isset($_REQUEST['debug'])) {
    echo $sql;
}
$results = $wpdb->get_results($sql);


wp_enqueue_script('req_list', TRCPDD_URL . 'js/admin_req_list.js', array(), TRCPDD_VS . time());

wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), TRCPDD_VS);

?>
<h2>Completed Req Forms</h2>
<?php Tr_Session_Class::show() ?>

<div class="tablenav">
    <form method="get" style="float:left;margin-right: 10px;">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <label>Name/Accession:</label>
        <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
        <input type="submit" class="button bsty6" value="Search" />
    </form>
    <?php if ($pagination) echo $pagination->pagination() ?>
</div>

<div class="wraptbl">
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width: 50px">#</th>
                <th>Accession Number</th>
                <th>PRACTICE</th>
                <th>Patient</th>
                <th style="width:80px">Date</th>
                <th style="width:160px;text-align: center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            foreach ($results as $row) {
                $printdata = array();
                $row->classes = '';
                $panels_print = array();
                $is_w = false;

                foreach (explode(',', $row->panel_tests_requested) as $t) {
                    if (stripos($t, 'group') === false) {
                        if ($t > 0 && isset($all_tests[$t])) {
                            $panel[] = $all_tests[$t]->te_name;
                            if ($all_tests[$t]->is_w) {
                                $is_w = true;
                            }
                        }
                    } else {
                        $group_id = str_ireplace('group_', '', $t);

                        if (isset($all_groups[$group_id])) {
                            $panel[] = $all_groups[$group_id]->tg_name;

                            if (isset($all_groups[$group_id]->tg_label_name) && !empty($all_groups[$group_id]->tg_label_name)) {
                                $panels_print[] = $all_groups[$group_id]->tg_label_name;
                            } else if (isset($all_groups[$group_id]->tg_notestcheckbox) && $all_groups[$group_id]->tg_notestcheckbox) {
                                foreach ($all_tests as $test) {
                                    if ($test->te_group_id == $group_id && !empty($test->te_label_barcode)) {
                                        $panels_print[] = $test->te_label_barcode;
                                    }
                                }
                            }
                        }
                    }
                }

                $printpanels = implode('|', $panels_print);

                $print_times =  tr_get_option('ci_pdf_data', 'req_print_times', 1);
                if ($print_times <= 0) $print_times = 1;

                if (isset($clis_print2) && is_array($clis_print2) && in_array($row->cli_ID, $clis_print2)) {
                    $print_times = 2;
                }

                if (isset($req_print3labels) && $req_print3labels) {
                    $print_times = 3;
                }


                if ($is_w) {
                    $printdata[] = array(
                        'assen' => substr($row->assen, 0, 2) . 'W' . substr($row->assen, 3),
                        'panel' => 'A1C',
                        'position' => 'W'
                    );
                }
                if (isset($settings['print_assen_specimen']) && $settings['print_assen_specimen']) {
                    foreach (explode(',', $row->type_of_specimen) as $spec) {

                        if (stripos($spec, 'req') !== false) {
                            $printdata[] = array(
                                'assen' => substr($row->assen, 0, 2) . 'W' . substr($row->assen, 3),
                                'panel' => '',
                                'position' => 'W'
                            );
                        } else if (stripos($spec, 'Urine') !== false) {
                            $printdata[] = array(
                                'assen' => substr($row->assen, 0, 2) . 'U' . substr($row->assen, 3),
                                'panel' => '',
                                'position' => 'U'
                            );
                        }
                    }
                }


                $row->classes .= ' bs_' . $row->batch_status;
            ?>
                <tr class="<?php echo $row->classes ?>" data-mid="<?php echo $row->mid ?>" <?php echo $print_times > 0 ? 'data-print_times="' . $print_times . '"' : '' ?> data-dob="<?php echo $row->dob ?>" data-doc="<?php echo $row->doc ?>" data-position="<?php echo $row->position ?>" data-printdata="<?php echo esc_attr(json_encode($printdata)) ?>" data-panels_print="<?php echo $printpanels ?>">
                    <td><?php echo $i++ ?></td>
                    <td>
                        <span class="assen"><?php echo $row->assen ?></span>
                        <div class="row-actions">
                            <a class="print_assen_btn" href="#">Print label</a> |
                            <a class="iprint" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'type' => 'req', 'tab' => '')) ?>&TB_iframe=true&width=800&height=550">Print</a>
                        </div>
                    </td>
                    <td><?php echo $row->cli_clinic ?></td>
                    <td><span class="col-patient_name"><?php echo $row->patient_name ?></span></td>
                    <td title="<?php echo $row->batch_created ?>"><?php echo $row->doc ?></td>
                    <td class="cactions medium">
                        <?php if ($can_edit && strtotime($row->doc) >= $edit_time) { ?>
                            <a class="bsty1" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => '')) ?>">Edit</a>
                        <?php } ?>
                        <?php
                        if ($can_hold) {
                        ?>
                            <a class="single_hold_btn bsty2" href="#"><?php echo $row->batch_status == 'hold' ? 'UnHold' : 'Hold' ?></a>
                        <?php
                        }
                        ?>
                        <a class="bsty3 thickbox" href="<?php Tr_Base_Class_V6::link(array('act' => 'view', 'id' => $row->mid, 'tab' => '', 'trajax' => 1, 'width' => 1300, 'height' => 700)) ?>">View</a>
                        <?php if ($can_delete && strtotime($row->doc) >= $edit_time) { ?>
                            <a class="delete " data-assen="<?php echo $row->assen ?>" href="#" data-href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->mid)) ?>">Delete</a>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
<script>
    jQuery(function($) {
        $('body').on('click', 'a.delete', function() {
            if (confirm('Are you sure you want to delete #' + $(this).data('assen') + '?') == false) return false;
            $(this).attr('href', $(this).data('href'));
        }).on('click', '.qc_cb', function() {
            var $mid = $(this).parents('tr').data('mid');
            var $checked = $(this).is(':checked') ? 1 : 0;
            $.ajax({
                type: 'post',
                dataType: 'json',
                data: {
                    trajax: 1,
                    act: 'change_qc',
                    mid: $mid,
                    checked: $checked
                },
                success: function(json) {

                }
            })
        })
    })
</script>