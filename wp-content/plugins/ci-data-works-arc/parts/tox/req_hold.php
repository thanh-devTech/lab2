<?php
//hold page

global $wpdb;
global $current_role;

$btype      = 'tox';
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
        ), $mid, $btype, '', '', $batch->assen);
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

        trpdd_log_user(get_current_user_id(), 'removehold_rs', $reason, $mid, $btype, '', '', $batch->assen);
    }
    wp_send_json($return);
}


$user_id    = get_current_user_id();

$conditions = trpdd_get_batch_conditions(0, 'tox', array('hold' => true));
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$can_remove_hold   = $conditions['can_remove_hold_req'];
$where          = '1 ' . $conditions['where'];
$where_clinic   = $conditions['where_clinic'];
if ($conditions['check_author']) {
    $where      .= $return['where_author'];
}

if ($act == 'delete' && $_GET['id'] > 0 && $can_delete) {
    trpdd_delete_batch($_GET['id'], 'tox');
    Tr_Session_Class::add('Deleted Req record');
}


if (isset($_REQUEST['not_short'])) {
    $where .= " and ba.batch_short != 1 ";
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



wp_enqueue_script('req_list', TRCPDD_URL . 'js/admin_req_list.js', array(), TRCPDD_VS . time());

wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), TRCPDD_VS);
?>
<h2>Reqs On Hold</h2>
<?php Tr_Session_Class::show() ?>

<div class="tablenav">
    <form method="get" style="float:left;margin-right: 10px;">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <label>Name/Accession:</label>
        <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? stripslashes(@$_REQUEST['s']) : '' ?>" />
        <input type="submit" class="button bsty6" value="Search" />
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
                <th>Reason</th>
                <th style="width:75px">Date</th>
                <th style="width:140px">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = ($pagination->get_paged() - 1) * $row_per_page + 1;
            foreach ($results as $row) {
                $hold_reason = $wpdb->get_var("select data 
                from ci_log_users 
                where user_action = 'hold_rs'
                and mid = {$row->mid} and btype='{$btype}' 
                order by id desc");

                $row->hold_reason = '';
                if (!empty($hold_reason)) {
                    $hold_reason_arr = json_decode($hold_reason, true);
                    if (is_array($hold_reason_arr)) {
                        $row->hold_reason = esc_html($hold_reason_arr['reason']);
                    } else {
                        $row->hold_reason = esc_html($hold_reason);
                    }
                }


                $row->classes = '';
                $row->classes .= ' bs_' . $row->batch_status;
                $row->is_hold = $row->batch_status == 'hold';
            ?>
                <tr class="<?php echo $row->classes ?>" data-mid="<?php echo $row->mid ?>" data-print_times="1" data-position="<?php echo $row->position; ?>" data-doc="<?php echo $row->doc ?>" data-dob="<?php echo $row->dob ?>">
                    <td><?php echo $i++ ?></td>
                    <td>
                        <span class="assen"><?php echo $row->assen ?></span>
                        <div class="row-actions">
                            <a class="print_assen_btn" href="#">Print Label</a> |
                            <a class="iprint " href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'tab' => '')) ?>&TB_iframe=true&width=800&height=550">Print</a>
                        </div>
                    </td>
                    <td><?php echo $row->cli_clinic ?></td>
                    <td><?php echo $row->requesting_physician ?></td>
                    <td class="col-patient_name"><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
                    <td><?php echo $row->hold_reason ?></td>
                    <td title="Modified: <?php echo $row->batch_modified ?>"><?php echo $row->doc ?></td>
                    <td class="cactions">
                        <?php if (strtotime($row->doc) >= $edit_time) : ?>
                            <a class="bsty1" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => '')) ?>" target="_blank">Edit</a>
                        <?php endif; ?>
                        <a class="bsty2" href="<?php Tr_Base_Class_V6::link(array('act' => 'view', 'id' => $row->mid, 'tab' => '')) ?>">View</a>
                        <?php
                        if ((!$row->is_hold && $can_hold) || ($row->is_hold && $can_remove_hold)) {
                        ?>
                            <a class="single_hold_btn bsty2" href="#"><?php echo $row->is_hold ? 'UnHold' : 'Hold' ?></a>
                        <?php
                        }
                        ?>
                    </td>
                </tr>
            <?php } ?>
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