<?php

global $wpdb;
global $current_role;

$btype     = 'tox';
$batch_tbl = tr_pdd_get_table_by_btype($btype);
$settings  = get_option('ci_req_settings_tox', array());
$print_times =  tr_get_option('ci_pdf_data', 'tox_print_times', 1);
$enable_duplicate = tr_get_option('ci_pdf_data', 'enable_duplicate_req') && in_array($current_role, array('administrator', 'office_manager', 'lab', 'lab_manager'));

$clinic_selected    = isset($_GET['cid']) ? $_GET['cid'] : 0;
$lid_selected       = isset($_GET['lid']) ? $_GET['lid'] : 0;

$user_id    = get_current_user_id();

$conditions = trpdd_get_batch_conditions(0, 'tox');
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where          = ' is_saved = 0 ' . $conditions['where'];
$where_clinic   = $conditions['where_clinic'];
if ($conditions['check_author']) {
    $where      .= $conditions['where_author'];
}


if (!empty($act)) {
    include_once(trpdd_get_file_path($btype, 'actions_req'));
}



if (!empty($_REQUEST['s'])) {
    $search = stripslashes($_REQUEST['s']);
    $mid = $wpdb->get_var("select mid from ci_batches where assen = '{$search}' ");
    if ($mid > 0) {
        $where .= " and ba.mid = {$mid} ";
    } else {
        if (strpos($_REQUEST['s'], ',') > 0) {
            list($firstname, $lastname) = explode(',', $_REQUEST['s']);
            $firstname = trim($firstname);
            $lastname = trim($lastname);
            $explodeString = "%{$firstname}%{$lastname}%";
            $where .= $wpdb->prepare(
                " and (ba.patient_name like %s or ba.requesting_practice like %s)",
                $explodeString,
                $search . '%'
            );
        } else {
            $where .= $wpdb->prepare(
                " and (ba.patient_name like %s or ba.assen like %s or ba.requesting_practice like %s)",
                '%' . $search . '%',
                $search . '%',
                $search . '%'
            );
        }
    }
}


if (isset($_REQUEST['not_short'])) {
    $where .= " and ba.batch_short != 1 ";
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
        from {$batch_tbl} ba
        where {$where} order by doc desc";
    $rs = $wpdb->get_results($sql);
    header("Content-disposition: attachment; filename=tox_export.csv");
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
        'type' => 'Tox Req List',
        'file_name' => 'export.csv',
        'total_records' => count($rs),
        'from_date' => $_REQUEST['fromdate'],
        'to_date' => $_REQUEST['todate'],
    ];
    trpdd_log_user(get_current_user_id(), 'download_reqs', $data_log, '', '', '', '', 'Reqs_' . date_i18n('mdY'));

    exit;
}

$row_per_page   = 100;
$pagination     = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches ba
        left join ci_clinic cli ON cli.cID = ba.cli_ID where  {$where}"));
$offset         = $pagination->offset();

$sql = "select
        ba.assen,
        ba.mid,
        ba.doc,
        ba.dob,
        ba.position,
        ba.batch_modified,
        ba.requesting_physician,
        ba.patient_name,
        ba.batch_status,
        if(ins.ins_company is not null, ins.ins_company,ba.insurance) as ins_company,
        cli.cli_clinic
        from ci_batches ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID
        where {$where} order by doc desc limit {$offset},{$row_per_page}";

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
        <thead class="sticky">
            <tr>
                <th style="width: 40px">#</th>
                <th style="width:120px">Accession</th>
                <th>PRACTICE</th>
                <th>ORDERING PHYSICIAN</th>
                <th>Patient</th>
                <th>INSURANCE</th>
                <th style="width:75px">Date</th>
                <th style="width:140px">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = ($pagination->get_paged() - 1) * $row_per_page + 1;
            foreach ($results as $row) {

                $row->classes = '';
                $row->classes .= ' bs_' . $row->batch_status;
            ?>
                <tr class="<?php echo $row->classes ?>" data-mid="<?php echo $row->mid ?>" <?php echo isset($print_times) && $print_times > 0 ? 'data-print_times="' . $print_times . '"' : '' ?> data-position="<?php echo $row->position; ?>" data-doc="<?php echo $row->doc ?>" data-dob="<?php echo $row->dob ?>">
                    <td><?php echo $i++ ?></td>
                    <td>
                        <span class="assen"><?php echo $row->assen ?></span>
                        <div class="row-actions">
                            <a class="print_assen_btn" href="#">Print Label</a> |
                            <a class="iprint " href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'tab' => '')) ?>&TB_iframe=true&width=800&height=550">Print</a>
                            <?php
                            if (!empty($enable_duplicate)) {
                                echo ' | <a onclick="return confirm(\'Are you sure you want to duplicate this order?\');" href="' . Tr_Base_Class_V6::link(array('tr_action' => 'ci_duplicate_req', 'id' =>  $row->mid, 'from' => $btype, 'to' => $btype, 'tab' => ''), false) . '" target=_blank>Duplicate</a>';
                            }
                            ?>
                        </div>
                    </td>
                    <td><?php echo $row->cli_clinic ?></td>
                    <td><?php echo $row->requesting_physician ?></td>
                    <td class="col-patient_name"><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
                    <td><?php echo $row->ins_company ?></td>
                    <td title="Modified: <?php echo $row->batch_modified ?>"><?php echo $row->doc ?></td>
                    <td class="cactions">
                        <?php if (strtotime($row->doc) >= $edit_time) : ?>
                            <a class="bsty1" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => '')) ?>">Edit</a>
                        <?php endif; ?>
                        <a class="bsty2" href="<?php Tr_Base_Class_V6::link(array('act' => 'view', 'id' => $row->mid, 'tab' => '')) ?>">View</a>
                        <?php
                        if ($can_hold) {
                        ?>
                            <a class="single_hold_btn bsty2" href="#"><?php echo $row->batch_status == 'hold' ? 'UnHold' : 'Hold' ?></a>
                        <?php
                        }
                        ?>
                        <?php if ($current_role == 'administrator' || $current_role == 'administrator_p') : ?>
                            <a class="delete bsty6 deleteajax" href="#" data-assen="<?php echo $row->assen ?>" data-id="<?php echo $row->mid ?>">Delete</a>
                        <?php endif; ?>
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