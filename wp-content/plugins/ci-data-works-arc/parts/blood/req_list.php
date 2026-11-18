<?php

global $wpdb;
global $current_role;
global $blog_id;
global $ci_all_btypes;

$blood_settings     = get_option('ci_req_settings_blood', array());
$btype              = 'blood';
$batch_tbl          = tr_pdd_get_table_by_btype($btype);
$batch_statuses     = tr_pdd_get_batch_statuses(false, false, false, $btype);
$show_duplicate_gene = in_array('gene', $ci_all_btypes);
$enable_duplicate = tr_get_option('ci_pdf_data', 'enable_duplicate_req') && in_array($current_role, array('administrator', 'office_manager', 'lab', 'lab_manager'));
$showcancelbtn      = tr_get_option('ci_pdf_data', 'ecancelorder', 0);

$user_id        = get_current_user_id();
$cls_id         = array();


$clinic_selected    = isset($_GET['cid']) ? $_GET['cid'] : 0;
$lid_selected       = isset($_GET['lid']) ? $_GET['lid'] : 0;

$all_tests = array();
$all_groups = array();
$reflex_ids = array();

$ts = $wpdb->get_results("select 
te_ID, 
te_name, 
te_label_barcode,
te_type_id
from ci_test_panels
where te_type = 'blood'");

foreach ($ts as $t) {

    $t->is_w = false;
    $t->is_u = false;
    if (isset($blood_settings['print_secondlb']) && $blood_settings['print_secondlb'] > 0) {
        if ($blood_settings['print_secondlb'] == $t->te_type_id) {
            $t->is_w = true;
        }
    }

    if (isset($blood_settings['print_secondlb_u']) && $blood_settings['print_secondlb_u'] > 0) {
        if ($blood_settings['print_secondlb_u'] == $t->te_type_id) {
            $t->is_u = true;
        }
    }

    if (!$t->is_w && stripos($t->te_label_barcode, 'HbA1c') !== false) {
        $t->is_w = true;
    }

    $all_tests[$t->te_ID] = $t;
}


$ts = $wpdb->get_results("select tg_ID, 
tg_name, 
tg_label_name,
tg_reflex_group_ID 
from ci_test_groups where tg_type = 'blood'");
foreach ($ts as $t) {
    $all_groups[$t->tg_ID] = $t;

    foreach (explode(',', $t->tg_reflex_group_ID) as $rflid) {
        if ($rflid > 0) {
            $reflex_ids[$rflid] = 'GROUP_' . $rflid;
        }
    }
}



$conditions = trpdd_get_batch_conditions(0, 'blood');

$where      = ' is_saved = 0 ' . $conditions['where'];

if ($conditions['check_author']) {
    $where      .= $return['where_author'];
}
$where_clinic   = $conditions['where_clinic'];

$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];



if (!empty($act)) {
    include_once(trpdd_get_file_path($btype, 'actions_req'));
}


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


if (!empty($_REQUEST['fromdate'])) {
    $where .= " and ba.doc >= '{$_REQUEST['fromdate']}' ";
}

if (!empty($_REQUEST['todate'])) {
    $where .= " and ba.doc <= '{$_REQUEST['todate']} 23:59:59' ";
}


if ($clinic_selected > 0) {
    $where .= " and ba.cli_ID = {$clinic_selected} ";
}

if ($lid_selected > 0) {
    $where .= " and (ba.ref_lab_ID = {$lid_selected} or ba.lab_ID = {$lid_selected} )";
}



if ($act == 'export_list') {


    header("Content-disposition: attachment; filename=blood_export.csv");
    header("Content-Type: text/csv");

    $join = '';
    $addingfield = '';

    $headers = array('Accession', 'Practice', 'Ordering Physician', 'Patient', 'Insurance', 'Date', 'Status');

    if ($blood_settings['show_phlebotomist_status']) {
        $headers[] = 'Phlebotomist Status';
    }


    $sql = "select
        ba.assen,
        ba.doc,
        ba.requesting_practice,
        ba.requesting_physician,
        ba.patient_name,
        ba.insurance,
        ba.batch_status
        {$addingfield}
        from {$batch_tbl} ba
        {$join}
        where {$where} order by doc desc";

    $rs = $wpdb->get_results($sql);

    $output = fopen("php://output", 'w');


    fputcsv($output, $headers);
    foreach ($rs as $row) {
        $rowarr = array(
            $row->assen,
            $row->requesting_practice,
            $row->requesting_physician,
            $row->patient_name,
            $row->insurance,
            $row->doc,
            $batch_statuses[$row->batch_status]
        );
        if ($blood_settings['show_phlebotomist_status']) {
            $rowarr[] = $batch_statuses[$row->batch_status];
        }

        fputcsv($output, $rowarr);
    }
    @fclose($output);
    $data_log = [
        'type' => 'Blood Req List',
        'file_name' => 'export.csv',
        'total_records' => count($rs),
        'from_date' => $_REQUEST['fromdate'],
        'to_date' => $_REQUEST['todate'],
    ];
    trpdd_log_user(get_current_user_id(), 'download_reqs', $data_log, '', '', '', '', 'Reqs_' . date_i18n('mdY'));

    exit;
}


$orderby            =  isset($_GET['orderby']) && !empty($_GET['orderby']) ? $_GET['orderby'] : 'doc';
$order              = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'asc' : 'desc';

$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_blood ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID where  {$where}"));
$offset = $pagination->offset();

$sql = "select
        ba.*,
        if(ins.ins_company is not null, ins.ins_company,ba.insurance) as ins_company,
        cli.cli_clinic
        from ci_batches_blood ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID
        where {$where} 
        order by {$orderby} {$order} 
        limit {$offset},{$row_per_page}";

if (isset($_REQUEST['debug'])) {
    echo $sql;
}
$results = $wpdb->get_results($sql);

$clinics = $wpdb->get_results("select cID, cli_clinic from ci_clinic where {$where_clinic}");

wp_enqueue_script('req_list', TRCPDD_URL . 'js/admin_req_list.js', array(), TRCPDD_VS . time());

wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), TRCPDD_VS);

?>
<h2>Completed Req Forms</h2>
<?php Tr_Session_Class::show() ?>

<div class="tablenav">
    <form method="get" id="formfilter" style="float:left;margin-right: 10px;">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <label>Name/Accession:</label>
        <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
        <input type="search" class="hasdate" name="fromdate" value="<?php echo isset($_REQUEST['fromdate']) ? $_REQUEST['fromdate'] : '' ?>" placeholder="From Date" />
        <input type="search" class="hasdate" name="todate" value="<?php echo isset($_REQUEST['todate']) ? $_REQUEST['todate'] : '' ?>" placeholder="To Date" />
        <select name="cid" class="js_select2" placeholder="Select Clinic" style="width:300px">
            <option value="">Select Clinic</option>
            <?php
            foreach ($clinics as $clinic) {
                $selected = $clinic->cID == $clinic_selected ? 'selected' : '';
                echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
            }
            ?>
        </select>
        <input type="submit" class="button bsty6" value="Search" />
        <input type="button" class="button export_list" value="Export" />
    </form>
    <?php if ($pagination) echo $pagination->pagination() ?>
</div>

<div class="wraptbl">
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width: 50px">#</th>
                <th>Accession Number</th>
                <?php
                if (isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist']) {
                    echo '<th style="width:50px">Drawn</th>';
                }
                ?>
                <th>PRACTICE</th>
                <th>ORDERING PHYSICIAN</th>
                <th>Patient</th>
                <th>INSURANCE</th>
                <?php if (tr_get_option('ci_req_settings_pcr', 'show_panel_list')) { ?>
                    <th>Panels</th>
                <?php } ?>
                <?php tr_tbl_sort('batch_status', 'Status', '', 'width:100px') ?>
                <?php tr_tbl_sort('doc', 'Date', '', 'width:100px') ?>
                <th style="width:160px;text-align: center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            foreach ($results as $row) {

                $row->classes = '';
                $row->panels = array();
                $row->print_panels = array();
                $row->print_panels_str = '';

                $printdata = array();
                $is_w = false;
                $is_u = false;
                $has_blank_group = false;
                $w_printbarcode = '';
                $u_printbarcode = '';

                foreach (explode(',', $row->panel_tests_requested) as $t) {
                    if (stripos($t, 'group') === false) {
                        if ($t > 0 && isset($all_tests[$t])) {

                            if ($all_tests[$t]->is_w) {
                                $is_w = true;
                                if (!empty($all_tests[$t]->te_label_barcode)) {
                                    $w_printbarcode = $all_tests[$t]->te_label_barcode;
                                } else if (stripos($all_tests[$t]->te_name, 'A1C') !== false) {
                                    $w_printbarcode = 'A1C';
                                }
                            }
                            if ($all_tests[$t]->is_u) {
                                $is_u = true;
                                if (!empty($all_tests[$t]->te_label_barcode)) {
                                    $u_printbarcode = $all_tests[$t]->te_label_barcode;
                                }
                            }
                        }
                    } else {
                        $group_id = str_ireplace('group_', '', $t);

                        if (isset($all_groups[$group_id])) {
                            $row->panels[] = $all_groups[$group_id]->tg_name;

                            if (isset($all_groups[$group_id]->tg_label_name) && !empty($all_groups[$group_id]->tg_label_name)) {
                                $row->print_panels[] = $all_groups[$group_id]->tg_label_name;
                            } else if (isset($all_groups[$group_id]->tg_notestcheckbox) && $all_groups[$group_id]->tg_notestcheckbox) {
                                foreach ($all_tests as $test) {
                                    if ($test->te_group_id == $group_id && !empty($test->te_label_barcode)) {
                                        $row->print_panels[] = $test->te_label_barcode;
                                    }
                                }
                            } else {
                                $has_blank_group = true;
                            }
                        }
                    }
                }


                if (count($reflex_ids) > 0) {
                    foreach ($reflex_ids as $rlid) {
                        unset($row->print_panels[$rlid]);
                    }
                }

                if ($has_blank_group && count($row->print_panels) > 0) {
                    $row->print_panels = array_merge(array(' ', ' '), $row->print_panels);
                }

                $row->print_panels_str = implode('|', $row->print_panels);

                $print_times =  tr_get_option('ci_pdf_data', 'blood_print_times', 1);
                if ($print_times <= 0) $print_times = 1;

                if (isset($clis_print2) && is_array($clis_print2) && in_array($row->cli_ID, $clis_print2)) {
                    $print_times = 2;
                }

                if (isset($blood_print3labels) && $blood_print3labels) {
                    $print_times = 3;
                }

                if (isset($blood_settings['print_assen_specimen']) && $blood_settings['print_assen_specimen']) {
                    foreach (explode(',', $row->type_of_specimen) as $spec) {
                        if (stripos($spec, 'blood') !== false) {
                            $printdata['W'] = array(
                                'assen' => substr($row->assen, 0, 2) . 'W' . substr($row->assen, 3),
                                'panel' => '',
                                'position' => 'W'
                            );
                        } else if (stripos($spec, 'Urine') !== false) {
                            $printdata['U'] = array(
                                'assen' => substr($row->assen, 0, 2) . 'U' . substr($row->assen, 3),
                                'panel' => '',
                                'position' => 'U'
                            );
                        }
                    }
                }

                if ($is_w) {
                    $printdata['W'] = array(
                        'assen' => substr($row->assen, 0, 2) . 'W' . substr($row->assen, 3),
                        'panel' => $w_printbarcode,
                        'position' => 'W'
                    );
                }
                if ($is_u) {
                    $printdata['U'] = array(
                        'assen' => substr($row->assen, 0, 2) . 'U' . substr($row->assen, 3),
                        'panel' => $u_printbarcode,
                        'position' => 'U'
                    );
                }


                if (empty($row->ins_company)) {
                    $row->ins_company  = $row->insurance;
                }

                if (!empty($row->patient_portal_type) && stripos($row->ins_company, 'No Insurance - Cash') !== false) {
                    $row->ins_company = '$ ' . $row->ins_company;
                }

                $row->classes .= ' bs_' . $row->batch_status;
                //no position for blood
                $row->position = '';
            ?>
                <tr class="<?php echo $row->classes ?>" data-mid="<?php echo $row->mid ?>" <?php echo $print_times > 0 ? 'data-print_times="' . $print_times . '"' : '' ?> data-dob="<?php echo $row->dob ?>" data-doc="<?php echo $row->doc ?>" data-printdata="<?php echo esc_attr(json_encode($printdata)) ?>" data-panels_print="<?php echo $row->print_panels_str ?>">
                    <td><?php echo $i++ ?></td>
                    <td>
                        <span class="assen"><?php echo $row->assen ?></span>
                        <div class="row-actions">
                            <a class="print_assen_btn" href="#">Print label</a> |
                            <a class="iprint" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'type' => 'blood', 'tab' => '')) ?>&TB_iframe=true&width=800&height=550">Print</a>
                            <?php
                            if ($show_duplicate_gene) {
                                echo ' | <a onclick="return confirm(\'Are you sure you want to duplicate this order to Genetics?\');" href="' . Tr_Base_Class_V6::link(array('tr_action' => 'ci_duplicate_req', 'id' =>  $row->mid, 'from' => 'blood', 'to' => 'gene', 'tab' => ''), false) . '" target=_blank>Duplicate to Gene</a>';
                            }

                            if (!empty($enable_duplicate)) {
                                echo ' | <a onclick="return confirm(\'Are you sure you want to duplicate this order?\');" href="' . Tr_Base_Class_V6::link(array('tr_action' => 'ci_duplicate_req', 'id' =>  $row->mid, 'from' => $btype, 'to' => $btype, 'tab' => ''), false) . '" target=_blank>Duplicate</a>';
                            }
                            ?>
                        </div>
                    </td>
                    <?php
                    if (isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist']) {
                    ?>
                        <td><input type="checkbox" class="qc_cb" <?php checked($row->fasting) ?> /></td>
                    <?php
                    }
                    ?>
                    <td><?php echo $row->cli_clinic ?></td>
                    <td><?php echo $row->requesting_physician ?></td>
                    <td><span class="col-patient_name"><?php echo $row->patient_name ?></span></td>
                    <td><?php echo $row->ins_company ?></td>
                    <?php if (tr_get_option('ci_req_settings_pcr', 'show_panel_list')) { ?>
                        <td><?php echo implode('<br>', $row->panels) ?></td>
                    <?php } ?>
                    <td><?php echo $batch_statuses[$row->batch_status] ?></td>
                    <td title="<?php echo $row->batch_created ?>"><?php echo $row->doc ?></td>
                    <td class="cactions medium">
                        <?php if ($can_edit && strtotime($row->doc) >= $edit_time) { ?>
                            <a class="bsty1" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => '')) ?>">Edit</a>
                        <?php } ?>
                        <a class="bsty3 thickbox" href="<?php Tr_Base_Class_V6::link(array('act' => 'view', 'id' => $row->mid, 'tab' => '', 'trajax' => 1, 'width' => 1300, 'height' => 700)) ?>">View</a>
                        <div class="row-actions">
                            <?php
                            if ($can_hold) {
                            ?>
                                <a class="single_hold_btn bsty2" href="#"><?php echo $row->batch_status == 'hold' ? 'UnHold' : 'Hold' ?></a>
                            <?php
                            }
                            if ($showcancelbtn) {
                            ?>
                                <a class="single_cancel_btn bsty2" href="#"><?php echo $row->batch_status == 'archived' ? 'Un-Archive' : 'Cancel Order' ?></a>
                            <?php
                            }
                            ?>

                            <?php if ($can_delete && strtotime($row->doc) >= $edit_time) { ?>
                                <a class="delete deleteajax" href="#" data-assen="<?php echo $row->assen ?>" data-id="<?php echo $row->mid ?>">Delete</a>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
<script>
    jQuery(function($) {
        $('body').on('click', '.qc_cb', function() {
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
        }).on('click', '.export_list', function() {

            var $url = '?' + $('#formfilter').serialize() + '&trajax=1&act=export_list';

            location = $url;
            return false;
        })
    })
</script>