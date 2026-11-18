<?php
global $wpdb;
global $current_role;

$btype     = 'gene';
$batch_tbl = tr_pdd_get_table_by_btype($btype);


$all_labs   = array();
$user_id    = get_current_user_id();
$cls_id     = array();
$where      = ' ';
$where2     = '';


$conditions = trpdd_get_batch_conditions(0, $btype);

$where      = ' is_saved = 0 ' . $conditions['where'];
$where_cli  = $conditions['where_clinic'];
if ($conditions['check_author']) {
    $where      .= $return['where_author'];
}

$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];

$clinic_selected    = isset($_GET['cid']) ? $_GET['cid'] : 0;
$lid_selected       = isset($_GET['lid']) ? $_GET['lid'] : 0;

$all_tests          = array();
$all_groups         = array();

$ts = $wpdb->get_results("select te_ID, 
                            te_name, 
                            te_label_barcode, 
                            te_group_id 
                            from ci_test_panels where te_type='gene'");
foreach ($ts as $t) {
    $all_tests[$t->te_ID] = $t;
}
$ts = $wpdb->get_results("select tg_ID, 
                                tg_name, 
                                tg_label_name,
                                tg_notestcheckbox 
                                from ci_test_groups
                                where tg_type='gene'");
foreach ($ts as $t) {
    $all_groups[$t->tg_ID] = $t;
}

$lrs = $wpdb->get_results("select * from ci_labs");
foreach ($lrs as $l) {
    $all_labs[$l->lab_ID] = $l->lab_name . ' -' . $l->lab_dba_init;
}


if (!empty($act)) {
    include_once(trpdd_get_file_path($btype, 'actions_req'));
}

if (!empty($_REQUEST['s'])) {
    $search = stripslashes(trim($_REQUEST['s']));

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
    $where .= " and ba.doc <= '{$_REQUEST['todate']}' ";
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
        from {$batch_tbl} ba
        where {$where} order by doc desc";
    $rs = $wpdb->get_results($sql);
    header("Content-disposition: attachment; filename=gene_export.csv");
    header("Content-Type: text/csv");
    $output = fopen("php://output", 'w');
    $headers = array('Accession', 'Practice', 'Ordering Physician', 'Patient', 'Insurance', 'Date');
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
        'type' => 'Gene Req List',
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
$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_gene ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID where  {$where}"));
$offset = $pagination->offset();

$sql = "select
        ba.assen,
        ba.mid,
        ba.doc,
        ba.batch_created,
        ba.dob,
        ba.requesting_physician,
        ba.patient_name,
        ba.is_employee,
        ba.panel_tests_requested,
        ba.culture,
        ba.position,
        ba.ref_lab_ID,
        if(ins.ins_company is not null, ins.ins_company,ba.insurance) as ins_company,
        cli.cli_clinic
        from ci_batches_gene ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID
        where {$where} order by doc desc limit {$offset},{$row_per_page}";

if (isset($_REQUEST['debug'])) {
    echo $sql;
    echo '<br><br><br>';
    echo '$all_labs:';
    var_dump($all_labs);
}
$results = $wpdb->get_results($sql);

$clinics = $wpdb->get_results("select cID, cli_clinic from ci_clinic where {$where_cli}");

?>
<h2>Completed Req Forms</h2>
<?php Tr_Session_Class::show() ?>

<div class="tablenav">
    <form method="get" style="float:left;margin-right: 10px;">
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
        <input type="submit" class="button bsty6" value="Search" />
        <input type="button" class="button export_list" onclick="location='<?php Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'export_list')) ?>'" value="Export" />
    </form>
    <?php if ($pagination) echo $pagination->pagination() ?>
</div>
<br><br>
<div class="wraptbl">
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl visitrow_tbl">
        <thead>
            <tr>
                <th style="width: 50px">#</th>
                <th>Accession Number</th>
                <th>PRACTICE</th>
                <th>ORDERING PHYSICIAN</th>
                <th>Patient</th>
                <th>INSURANCE</th>
                <th style="width:80px">Date</th>
                <th style="width:120px;text-align: center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            foreach ($results as $row) {

                $panels_print = array();

                foreach (explode(',', $row->panel_tests_requested) as $t) {
                    if (stripos($t, 'group') === false) {
                        if ($t > 0 && isset($all_tests[$t])) {
                            $panel[] = $all_tests[$t]->te_name;

                            if (isset($all_tests[$t]->te_label_barcode) && !empty($all_tests[$t]->te_label_barcode)) {
                                $panels_print[] = $all_tests[$t]->te_label_barcode;
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

                $ref_lab_name = '';
                if ($row->ref_lab_ID > 0) {
                    $ref_lab_name = $all_labs[$row->ref_lab_ID];
                }

            ?>
                <tr data-mid="<?php echo $row->mid ?>" data-dob="<?php echo $row->dob ?>" data-cul="<?php echo $row->culture ?>" data-doc="<?php echo $row->doc ?>" data-position="<?php echo $row->position ?>" data-panels_print="<?php echo $printpanels ?>">
                    <td><?php echo $i++ ?></td>
                    <td><span class="assen"><?php echo $row->assen ?></span>
                        <div class="row-actions">
                            <a class="print_assen_btn" href="#">Print label</a> |
                            <a class="iprint " href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'type' => 'gene', 'tab' => '')) ?>">Print</a>
                        </div>
                    </td>
                    <td title="<?php echo $ref_lab_name ?>"><?php echo $row->cli_clinic ?></td>
                    <td><?php echo $row->requesting_physician ?></td>
                    <td><span class="col-patient_name"><?php echo $row->patient_name ?></span>
                        <?php if ($row->is_employee) echo '<span title="Employee" class="checkmark checked small"></span>' ?></td>
                    <td><?php echo $row->ins_company ?></td>
                    <td title="<?php echo $row->batch_created ?>"><?php echo $row->doc ?></td>
                    <td class="cactions">
                        <?php if ($can_edit && strtotime($row->doc) >= $edit_time) { ?>
                            <a class="bsty1" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => '')) ?>">Edit</a>
                        <?php } ?>
                        <div class="row-actions">
                            <a class="thickbox" target="_blank" href="<?php Tr_Base_Class_V6::link(array('act' => 'view', 'id' => $row->mid, 'tab' => '', 'trajax' => 1, 'width' => 1300, 'height' => 700)) ?>">View</a>
                            <?php if ($can_delete && strtotime($row->doc) >= $edit_time) { ?>
                                <a class="delete bsty6 deleteajax" href="#" data-assen="<?php echo $row->assen ?>" data-id="<?php echo $row->mid ?>">Delete</a>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>