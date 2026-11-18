<?php
global $wpdb;
global $current_role;


$batch_tbl = 'ci_batches_pcr';
$today = date('Y-m-d', time() + (get_option('gmt_offset') * HOUR_IN_SECONDS));
$edit_time = strtotime($today);

$user_id = get_current_user_id();
$cls_id = array();
$where = ' ';

$conditions = trpdd_get_batch_conditions(0, 'pcr');
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where          = ' is_saved = 1 ' . $conditions['where'];
$where_clinic   = $conditions['where_clinic'];
if ($conditions['check_author']) {
    $where      .= $return['where_author'];
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
        $where .= $wpdb->prepare(" and (ba.patient_name like %s )", $explodeString);
    } else {
        $where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen like %s)", '%' . $search . '%', $search . '%');
    }
}

$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c 
        from {$batch_tbl} ba
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
        from {$batch_tbl} ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
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
        <input type="text" name="s" value="<?php echo stripslashes(isset($_REQUEST['s']) ? $_REQUEST['s'] : '') ?>" />
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
                foreach ($results as $row) { ?>
                    <tr>
                        <td><?php echo $i++ ?></td>
                        <td><?php echo $row->assen ?></td>
                        <td><?php echo $row->cli_clinic ?></td>
                        <td><?php echo $row->requesting_physician ?></td>
                        <td><?php echo $row->patient_name ?></td>
                        <td><?php echo $row->ins_company ?></td>
                        <td><?php echo $row->doc ?></td>
                        <td class="cactions">
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid)) ?>">Edit</a>
                            <a class="delete bsty6 deleteajax" href="#" data-assen="<?php echo $row->assen ?>" data-id="<?php echo $row->mid ?>">Delete</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>


</div>