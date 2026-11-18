<?php
global $wpdb;
global $current_role;



$conditions = trpdd_get_batch_conditions(0, 'req');
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where          = ' is_saved = 1 ' . $conditions['where'];
$where_clinic   = $conditions['where_clinic'];
if ($conditions['check_author']) {
    $where      .= $return['where_author'];
}


$today         = date('Y-m-d', time() + (get_option('gmt_offset') * HOUR_IN_SECONDS));
$edit_time     = strtotime($today);

$user_id     = get_current_user_id();
$cls_id     = array();

if ($act == 'delete' && $_GET['id'] > 0 && $can_delete) {
    $wpdb->delete('ci_batches_req', array('mid' => $_GET['id']));
    Tr_Session_Class::add('Deleted Req record');
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
        $where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen like %s)", '%' . $search . '%', $search . '%');
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
        ba.doc,
        ba.requesting_physician,
        ba.patient_name,
        cli.cli_clinic
        from ci_batches_req ba
        left join ci_clinic cli ON cli.cID = ba.cli_ID
        where {$where} order by doc desc limit {$offset},{$row_per_page}";

if (isset($_REQUEST['debug'])) {
    echo $sql;
}
$results = $wpdb->get_results($sql);

?>
<div class="wrap">
    <?php Tr_Session_Class::show() ?>


    <form method="get" style="float:left;margin-right: 10px;">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <label>Name/Accession:</label>
        <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
        <input type="submit" class="button bsty6" value="Search" />
    </form>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

    <div class="wraptbl">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width: 50px">#</th>
                    <th>Accession Number</th>
                    <th>PRACTICE</th>
                    <th>Patient</th>
                    <th style="width:80px">Date</th>
                    <th style="width:120px;text-align: center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 1;
                foreach ($results as $row) { ?>
                    <tr>
                        <td><?php echo $i++ ?></td>
                        <td><?php echo $row->assen ?></td>
                        <td><?php echo $row->cli_clinic ?></td>
                        <td><?php echo $row->patient_name ?></td>
                        <td><?php echo $row->doc ?></td>
                        <td class="cactions">
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid)) ?>">Edit</a>
                            <a class="delete bsty6" data-assen="<?php echo $row->assen ?>" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->mid)) ?>">Delete</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
</div>
<script>
    jQuery(function($) {
        $('a.delete').click(function() {
            if (confirm('Are you sure you want to delete #' + $(this).data('assen') + '?') == false) return false;
        })
    })
</script>