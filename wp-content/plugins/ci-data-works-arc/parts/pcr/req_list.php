<?php
global $wpdb;
global $current_role;

$btype              = 'pcr';
$batch_tbl          = tr_pdd_get_table_by_btype($btype);
$batch_statuses     = tr_pdd_get_batch_statuses(false, false, false, 'pcr');
$batch_statuses['racked'] = 'Racked';

$enable_duplicate = tr_get_option('ci_pdf_data', 'enable_duplicate_req') && in_array($current_role, array('administrator', 'office_manager', 'lab', 'lab_manager'));


$conditions = trpdd_get_batch_conditions(0, 'pcr');
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where          = ' is_saved = 0 ' . $conditions['where'];
$where_clinic   = $conditions['where_clinic'];
if ($conditions['check_author']) {
    $where      .= $conditions['where_author'];
}

$archive_date       = tr_get_option('ci_pdf_data', 'archive_date', '');
$show_archive       = isset($_GET['showarchive']);
if (!empty($archive_date) && !$show_archive) {
    $where .= " and ba.doc >' {$archive_date}' ";
}

$print_times        = tr_get_option('ci_pdf_data', 'pcr_print_times', 1);
$showcancelbtn      = tr_get_option('ci_pdf_data', 'ecancelorder', 0);
$user_id            = get_current_user_id();

$clinic_selected    = isset($_GET['cid']) ? $_GET['cid'] : 0;
$lid_selected       = isset($_GET['lid']) ? $_GET['lid'] : 0;

$all_tests          = array();
$all_groups         = array();
$reflex_ids         = array();
$all_labs           = array();

$ts = $wpdb->get_results("select te_ID, 
                            te_name, 
                            te_label_barcode, 
                            te_group_id 
                            from ci_test_panels where te_type='pcr'");
foreach ($ts as $t) {
    $all_tests[$t->te_ID] = $t;
}
$ts = $wpdb->get_results("select * from ci_test_groups
                                where tg_type='pcr'");
foreach ($ts as $t) {
    $all_groups[$t->tg_ID] = $t;

    foreach (explode(',', $t->tg_reflex_group_ID) as $rflid) {
        if ($rflid > 0) {
            $reflex_ids[$rflid] = 'GROUP_' . $rflid;
        }
    }
}



$lrs = $wpdb->get_results("select * from ci_labs");
foreach ($lrs as $l) {
    $all_labs[$l->lab_ID] = $l->lab_name . ' -' . $l->lab_dba_init;
}


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
        $where .= $wpdb->prepare(" and (
                                            ba.patient_name like %s 
                                            or ba.assen like %s
                                            or ba.oldreq = %s
                                            )", '%' . $search . '%', $search . '%', $search);
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
    $sql = "select
        ba.assen,
        ba.doc,
        ba.requesting_practice,
        ba.requesting_physician,
        ba.patient_name,
        ba.insurance
        from ci_batches_pcr ba
        where {$where} order by doc desc";
    $rs = $wpdb->get_results($sql);
    header("Content-disposition: attachment; filename=export.csv");
    header("Content-Type: text/csv");
    $output = fopen("php://output", 'w');
    $headers = array('Accession', 'PRACTICE', 'ORDERING PHYSICIAN', 'Patient', 'Insurance', 'Date');
    fputcsv($output, $headers);
    foreach ($rs as $r) {
        $row = array(
            $r->assen,
            $r->requesting_practice,
            $r->requesting_physician,
            $r->patient_name,
            $r->insurance,
            $r->doc
        );

        fputcsv($output, $row);
    }
    @fclose($output);
    $data_log = [
        'type' => 'PCR Req List',
        'file_name' => 'export.csv',
        'total_records' => count($rs),
        'from_date' => $_REQUEST['fromdate'],
        'to_date' => $_REQUEST['todate'],
    ];
    trpdd_log_user(get_current_user_id(), 'download_reqs', $data_log, '', '', '', '', 'Reqs_' . date_i18n('mdY'));

    exit;
}


$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_pcr ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID where  {$where}"));
$offset = $pagination->offset();

$orderby            =  isset($_GET['orderby']) && !empty($_GET['orderby']) ? $_GET['orderby'] : 'doc';
$order              = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'asc' : 'desc';

$sql = "select
        ba.assen,
        ba.oldreq,
        ba.mid,
        ba.doc,
        ba.batch_created,
        ba.dob,
        ba.requesting_physician,
        ba.patient_name,
        ba.is_employee,
        ba.panel_tests_requested,
        ba.culture,
        ba.batch_type,
        ba.position,
        ba.ref_lab_ID,
        ba.batch_status,
        if(ins.ins_company is not null, ins.ins_company,ba.insurance) as ins_company,
        cli.cli_clinic
        from ci_batches_pcr ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID
        where {$where} 
        order by {$orderby} {$order}
        limit {$offset},{$row_per_page}";

if (isset($_REQUEST['debug'])) {
    echo $sql;
    echo '<br><br><br>';
    echo '$all_labs:';
    var_dump($all_labs);
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
        <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? stripslashes($_REQUEST['s']) : '' ?>" />
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
        <?php if (!empty($archive_date)) {
            echo '<label><input type="checkbox" name="showarchive" onclick="this.form.submit()" value=1 ' . checked($show_archive, true, false) . '/> Show Archived</label>';
        }
        ?>
        <input type="submit" class="button bsty6" value="Search" />
        <input type="button" class="button export_list" value="Export" />
    </form>
    <?php if ($pagination) echo $pagination->pagination() ?>
</div>
<br><br>
<div class="wraptbl">
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl visitrow_tbl">
        <thead>
            <tr>
                <th style="width: 50px">#</th>
                <?php tr_tbl_sort('assen', 'Accession Number') ?>
                <?php if (tr_get_option('ci_req_settings_pcr', 'show_refasseninlist')) { ?>
                    <th style="width:80px"><?php echo tr_get_option('ci_pdf_data', 'title_pid_field', 'Order') ?></th>
                <?php } ?>
                <th>PRACTICE</th>
                <th>ORDERING PHYSICIAN</th>
                <th>Patient</th>
                <th>INSURANCE</th>
                <?php if (tr_get_option('ci_req_settings_pcr', 'show_panel_list')) { ?>
                    <th>Panels</th>
                <?php } ?>
                <?php tr_tbl_sort('batch_status', 'Status', '', 'width:100px') ?>
                <?php tr_tbl_sort('doc', 'Date', '', 'width:100px') ?>
                <th style="width:140px;text-align: center">Actions</th>
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

                foreach (explode(',', $row->panel_tests_requested) as $t) {
                    if (stripos($t, 'group') === false) {
                        if ($t > 0 && isset($all_tests[$t])) {

                            if (isset($all_tests[$t]->te_label_barcode) && !empty($all_tests[$t]->te_label_barcode)) {
                                $row->print_panels[$t] = $all_tests[$t]->te_label_barcode;
                            }
                        }
                    } else {
                        $group_id = str_ireplace('group_', '', $t);

                        if (isset($all_groups[$group_id])) {
                            $row->panels[] = $all_groups[$group_id]->tg_name;

                            if (isset($all_groups[$group_id]->tg_label_name) && !empty($all_groups[$group_id]->tg_label_name)) {
                                $row->print_panels[$t] = $all_groups[$group_id]->tg_label_name;
                            } else if (isset($all_groups[$group_id]->tg_notestcheckbox) && $all_groups[$group_id]->tg_notestcheckbox) {
                                foreach ($all_tests as $test) {
                                    if ($test->te_group_id == $group_id && !empty($test->te_label_barcode)) {
                                        $row->print_panels[$test->te_ID] = $test->te_label_barcode;
                                    }
                                }
                            }
                        }
                    }
                }


                if (count($reflex_ids) > 0) {
                    foreach ($reflex_ids as $rlid) {
                        unset($row->print_panels[$rlid]);
                    }
                }


                if (tr_get_option('ci_pdf_data', 'mp_lb')) {
                    $row->print_panels_str = implode(' ', $row->print_panels);
                } else {
                    $row->print_panels_str = implode('|', $row->print_panels);
                }

                $row->ref_lab_name = '';
                if ($row->ref_lab_ID > 0) {
                    $row->ref_lab_name = $all_labs[$row->ref_lab_ID];
                }

                $row->classes .= ' bs_' . $row->batch_status;

                $row->edit_link = Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => ''), false);
                if ($row->batch_type == 'P') {
                    $row->edit_link = Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => 'req_form2'), false);
                }

            ?>
                <tr class="<?php echo $row->classes ?>" data-mid="<?php echo $row->mid ?>" data-print_times="<?php echo $print_times ?>" data-dob="<?php echo $row->dob ?>" data-cul="<?php echo $row->culture ?>" data-doc="<?php echo $row->doc ?>" data-panels_print="<?php echo $row->print_panels_str ?>">
                    <td><?php echo $i++ ?></td>
                    <td><span class="assen"><?php echo $row->assen ?></span>
                        <div class="row-actions">
                            <a class="print_assen_btn" href="#">Print label</a> |
                            <a class="iprint " href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'type' => 'pcr', 'tab' => '')) ?>">Print</a>
                            <?php
                            if (!empty($enable_duplicate)) {
                                echo ' | <a onclick="return confirm(\'Are you sure you want to duplicate this order?\');" href="' . Tr_Base_Class_V6::link(array('tr_action' => 'ci_duplicate_req', 'id' =>  $row->mid, 'from' => $btype, 'to' => $btype, 'tab' => ''), false) . '" target=_blank>Duplicate</a>';
                            }
                            ?>
                        </div>
                    </td>
                    <?php if (tr_get_option('ci_req_settings_pcr', 'show_refasseninlist')) echo '<td>' . $row->oldreq . '</td>' ?>
                    <td title="<?php echo $row->ref_lab_name ?>"><?php echo $row->cli_clinic ?></td>
                    <td><?php echo $row->requesting_physician ?></td>
                    <td><span class="col-patient_name"><?php echo $row->patient_name ?></span>
                        <?php if ($row->is_employee) echo '<span title="Employee" class="checkmark checked small"></span>' ?></td>
                    <td><?php echo $row->ins_company ?></td>
                    <?php if (tr_get_option('ci_req_settings_pcr', 'show_panel_list')) { ?>
                        <td><?php echo implode('<br>', $row->panels) ?></td>
                    <?php } ?>
                    <td><?php echo $batch_statuses[$row->batch_status] ?></td>
                    <td title="<?php echo $row->batch_created ?>"><?php echo $row->doc ?></td>
                    <td class="cactions">
                        <?php if ($can_edit && strtotime($row->doc) >= $edit_time) { ?>
                            <a class="bsty1" href="<?php echo $row->edit_link ?>">Edit</a>
                        <?php } ?>

                        <a class="thickbox" href="<?php Tr_Base_Class_V6::link(array('act' => 'view', 'id' => $row->mid, 'tab' => '', 'trajax' => 1, 'width' => 1300, 'height' => 700)) ?>">View</a>


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
        $('body').on('click', '.export_list', function() {

            var $url = '?' + $('#formfilter').serialize() + '&trajax=1&act=export_list';

            location = $url;
            return false;
        })
    })
</script>