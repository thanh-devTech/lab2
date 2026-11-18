<?php

global $wpdb;
global $current_role;
global $blog_id;

$today = date('Y-m-d', time() + (get_option('gmt_offset') * HOUR_IN_SECONDS));
$edit_time = strtotime($today);
$edit_time -= 86400 * 60;

$user_id = get_current_user_id();
$cls_id = array();
$where = ' ';
$where2 = '';


if ($act == 'delete' && $_GET['id'] > 0) {
    trpdd_delete_batch($_GET['id'], 'ngs');
    Tr_Session_Class::add('Deleted Req record');
}

$check_author = false;
switch ($current_role) {
    case 'administrator_p':
    case 'administrator':
    case 'AdminCIDW':
        $edit_time = 0;
        break;
    case 'lab_rep':
    case 'lab_rep_w2':

        $rep_id = get_user_meta($user_id, '_rep_id', true);
        $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


        $clis = $wpdb->get_results("select * from ci_clinic where `cli_rep-ID`={$rep_id}", ARRAY_A);
        foreach ($clis as $row) {
            $cls_id[] = $row['cID'];
        }
        $cls_id = implode(', ', $cls_id);
        if (!empty($cls_id)) {
            $where .= " and ba.cli_ID in  ({$cls_id})";
        }
        $check_author = false;
        break;

    case 'lab':
    case 'labb':

        break;

    case 'lab_tech':

        $edit_time -= 86400 * 1000;
        break;

    case 'collector';
        $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
        if ($current_clinic > 0) {
            $where .= " and ba.cli_ID = {$current_clinic} ";
        }
        $col_id = get_user_meta($user_id, '_collector_id', true);
        if (!tr_get_option('ci_pdf_data', 'collector_see_all')) {
            $where .= " and ba.col_ID = {$col_id} ";
        }
        $check_author = false;
        $edit_time -= 86400 * 4;
        break;

    case 'doctor':
        $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
        if ($current_clinic > 0) {
            $where .= " and ba.cli_ID = {$current_clinic} ";
        }
        $doc_id = get_user_meta($user_id, '_doc_id', true);
        if (!tr_get_option('ci_pdf_data', 'doctor_see_all')) {
            $where .= " and ba.doc_ID = {$doc_id} ";
        }
        $check_author = false;
        break;
    case 'lab_director':
        $where .= " and ba.cli_ID >0";
        //$check_author = true;
        break;
    case 'lab_manager':
        $where .= " and ba.cli_ID >0";
        $check_author = true;
        break;
    case 'clinic':
        $check_author = true;
        $edit_time -= 86400 * 4;
        break;
    case 'office_manager':
        $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
        if ($current_clinic > 0) {
            $where .= " and ba.cli_ID = {$current_clinic} ";
        } else {
            if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
                $cid     = get_user_meta($user_id, '_clinic_id', true);
                $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
                $cli_ids = implode(',', $cli_ids);

                $where .= " and ba.cli_ID in ( {$cli_ids} )";
            }
        }
        if (tr_get_option('ci_pdf_data', 'aoffice_delete')) {
            $can_delete = true;
        }
        $edit_time -= 86400 * 4;
        break;
    case 'multi_site_manager':
        $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
        if ($current_clinic > 0) {
            $where .= " and ba.cli_ID = {$current_clinic} ";
        } else  if (tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
            //can see all clinics
        } else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
            if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
                $ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
                update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
            }
            $ms_cli_ids_str = implode(',', $ms_cli_ids);
            $where .= " and ba.cli_ID in ( {$ms_cli_ids_str} )";
        }
        break;
    case 'lab_sec':
        $edit_time = 0;
        break;

    case 'leadlabsecman':
    case 'labsecman':
        $edit_time -= 86400 * 365;
        break;

    default:
        $check_author = true;
        $edit_time -= 86400 * 4;
        break;
}


$where_author = '';
if ($check_author) {
    $user_ids = tr_dpp_get_users_in_group($user_id);
    $user_ids = implode(',', $user_ids);
    $where_author .= " and ba.author_ID in ( {$user_ids} ) ";
    $where .= $where_author;
}


if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where2 .= " or (ba.ref_lab_ID in ( {$lab_str} ) {$where_author})";
    $where .= " and ba.lab_ID in ( {$lab_str} ) ";
}


$where = "((( 1 {$where}) {$where2}) )";

if (!empty($_REQUEST['s'])) {
    $search = stripslashes($_REQUEST['s']);

    if (strpos($_REQUEST['s'], ',') > 0) {
        list($firstname, $lastname) = explode(',', $_REQUEST['s']);
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        $explodeString = "%{$firstname}%{$lastname}%";
        $where .= $wpdb->prepare(" and (ba.patient_name like %s)", $explodeString);
    } else {
        $where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen like %s)", '%' . $search . '%', $search . '%');
    }
}

$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_ngs ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID where  {$where}"));
$offset = $pagination->offset();

$sql = "select
        ba.assen,
        ba.mid,
        ba.doc,
        ba.requesting_physician,
        ba.patient_name,
        if(ins.ins_company is not null, ins.ins_company,ba.insurance) as ins_company,
        cli.cli_clinic
        from ci_batches_ngs ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID
        where {$where} order by doc desc limit {$offset},{$row_per_page}";

if (isset($_REQUEST['debug'])) {
    echo $sql;
}
$results = $wpdb->get_results($sql);

?>
<h2>Completed Req Forms</h2>
<?php Tr_Session_Class::show() ?>


<form method="get" style="float:left;margin-right: 10px;">
    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
    <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
    <label>Name/Accession:</label>
    <input type="text" name="s" value="<?php echo stripslashes(@$_REQUEST['s']) ?>" />
    <input type="submit" class="button bsty6" value="Search" />
</form>
<a style="float:right;margin-right:20px;" class="button thickbox" href="?tr_action=ci_export_req_all_pdf&type=ngs">Export PDF</a>
<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

<div class="wraptbl">
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width: 50px">#</th>
                <th>Accession Number</th>
                <th>PRACTICE</th>
                <th>ORDERING PHYSICIAN</th>
                <th>Patient</th>
                <th>INSURANCE</th>
                <th style="width:75px">Date</th>
                <th style="width:200px">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            foreach ($results as $row): ?>
                <tr>
                    <td><?php echo $i++ ?></td>
                    <td><?php echo $row->assen ?></td>
                    <td><?php echo $row->cli_clinic ?></td>
                    <td><?php echo $row->requesting_physician ?></td>
                    <td><?php echo $row->patient_name ?></td>
                    <td><?php echo $row->ins_company ?></td>
                    <td><?php echo $row->doc ?></td>
                    <td>
                        <?php if (strtotime($row->doc) >= $edit_time): ?>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => '')) ?>">Edit</a> -
                        <?php endif; ?>
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'view', 'id' => $row->mid, 'tab' => '')) ?>">View</a>
                        - <a href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_export_req_pdf', 'id' => $row->mid)) ?>">PDF</a>
                        - <a class="iprint" data-href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'type' => 'ngs', 'tab' => '')) ?>&TB_iframe=true&width=800&height=550">Print</a>
                        <?php if ($current_role == 'administrator' || $current_role == 'administrator_p' || $current_role == 'AdminCIDW'): ?>
                            - <a class="delete" data-assen="<?php echo $row->assen ?>" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->mid)) ?>">Delete</a>

                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
<script>
    jQuery(function($) {
        $('a.delete').click(function() {
            if (confirm('Are you sure you want to delete #' + $(this).data('assen') + '?') == false) return false;
        })
    })
</script>