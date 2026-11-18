<?php
include_once(TRCPDD_PATH . 'pages/formdata.php');
$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : @$act;
if (in_array($act, array('edit', 'view', 'print', 'delete'))) {
    include_once(tr_pdd_get_req_file('tox'));
    return;
}

global $wpdb;
global $current_role;
global $blog_id;

$today = date('Y-m-d', time() + (get_option('gmt_offset') * HOUR_IN_SECONDS));

$user_id = get_current_user_id();
$cls_id = array();
$where = ' where ba.batch_id = 0 and ba.batch_short = 1 ';

if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $rep_id = get_user_meta($user_id, '_rep_id', true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


    $clis = $wpdb->get_results("select * from ci_clinic where `cli_rep-ID`={$rep_id}", ARRAY_A);
    foreach ($clis as $row) {
        $cls_id[] = $row['cID'];
    }
    $cls_id = implode(', ', $cls_id);
    $where .= " and ba.cli_ID in  ({$cls_id})";
} else if ($current_role == 'lab') {
} else if ($current_role == 'lab_tech') {
} else if ($current_role == 'collector') {
    $where .= " and ba.author_ID = {$user_id}";
} else if ($current_role == 'doctor') {
    $where .= " and ba.author_ID = {$user_id}";
} else if ($current_role == 'clinic') {
    $where .= " and ba.author_ID = {$user_id}";
} else if ($current_role == 'lab_director') {
    $where .= " and ba.cli_ID >0";
} else if ($current_role == 'lab_manager') {
    $where .= " and ba.cli_ID >0";
}

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $sql .= " and ba.lab_ID in ( {$lab_str} ) ";
}


if ($act == 'delete' && $_GET['id'] > 0) {
    $wpdb->delete('ci_batches', array('mid' => $_GET['id']));
    Tr_Session_Class::add('Deleted Req record');
}

$sql = "select
ba.assen,
ba.mid,
ba.doc,
ba.requesting_physician,
ba.patient_name,
ba.short_completed,
if(ins.ins_company is not null, ins.ins_company,ba.insurance) as ins_company,
cli.cli_clinic
from ci_batches ba
left join ci_insurances ins ON ins.ins_ID = ba.insurance1
left join ci_clinic cli ON cli.cID = ba.cli_ID
{$where} order by doc desc limit 300";
$results = $wpdb->get_results($sql);

if (isset($_GET['debug'])) {
    echo $sql;
}

?>
<h2>Short Reqs</h2>
<?php Tr_Session_Class::show() ?>

<div class="wraptbl">
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead class="sticky">
            <tr>
                <th style="width: 50px">#</th>
                <th>Accession Number</th>
                <th>PRACTICE</th>
                <th>ORDERING PHYSICIAN</th>
                <th>Patient</th>
                <th>INSURANCE</th>
                <th style="width:75px">Date</th>
                <th>Completed</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            if (count($results) == 0) {
            ?>
                <tr>
                    <td colspan="8">No Results</td>
                </tr>
                <?php
            } else {
                foreach ($results as $row) : ?>
                    <tr>
                        <td><?php echo $i++ ?></td>
                        <td><?php echo $row->assen ?></td>
                        <td><?php echo $row->cli_clinic ?></td>
                        <td><?php echo $row->requesting_physician ?></td>
                        <td><?php echo $row->patient_name ?></td>
                        <td><?php echo $row->ins_company ?></td>
                        <td><?php echo $row->doc ?></td>
                        <td><?php echo ($row->short_completed) == '1' ? '<green>Yes</green>' : '<red>No</red>' ?></td>
                        <td>

                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => '')) ?>">Edit</a> -

                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'view', 'id' => $row->mid, 'tab' => '')) ?>">View</a>
                            - <a class="thickbox" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'tab' => '')) ?>&TB_iframe=true&width=800&height=550">Print</a>
                            <?php if ($current_role == 'administrator' || $current_role == 'administrator_p') : ?>
                                - <a class="delete" data-assen="<?php echo $row->assen ?>" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->mid)) ?>">Delete</a>

                            <?php endif; ?>
                        </td>
                    </tr>
            <?php endforeach;
            }
            ?>
        </tbody>
    </table>
</div>

<script>
    jQuery(function($) {
        $('a.delete').click(function() {
            if (confirm('Are you sure you want to delete #' + $(this).data('assen') + '?') == false) return false;
        })
    })
</script>