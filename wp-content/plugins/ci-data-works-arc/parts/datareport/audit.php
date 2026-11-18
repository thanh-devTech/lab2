<?php
global $wpdb, $current_role, $blog_id;


wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('jquery-validate', TRCPDD_URL . 'admin/js/jquery.validate.min.js', array('jquery'), true);
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to   = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$lab_id = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
$sort = isset($_REQUEST['sort']) && $_REQUEST['sort'] == 'desc' ? 'desc' : 'asc';
$sortby = isset($_REQUEST['sortby']) ? $_REQUEST['sortby'] : 'doc';

if (isset($_REQUEST['submit'])) {
    if (empty($date_from)) $date_from = date_i18n('Y-m-d');
    if (empty($date_to)) $date_to = date_i18n('Y-m-d');


    $where = " and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to}' ";


    if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $where .= " and ba.lab_id in ( {$lab_str} )  ";
    }

    $headers = array(
        // '#',
        'batch_id' => 'Batch#',
        'assen' => 'Accession',
        'doc' => 'D.O.C',
        // 'id'=> 'ID#',
        'patient_id' => 'Patient ID',
        'patient_name' => 'Patient Name',
        'requesting_physician' => 'dname',
        'distributor' => 'Distributor',
        'rep' => 'Rep',
        'ref_idno' => 'ref_idno',
        'refname' => 'refname',
        'pinsname' => 'pinsname',
        'insurance' => 'sinsname',
        'approved' => 'approved',
        'collected' => 'collected',
        'adjusted' => 'adjusted',
        'patpay' => 'patpay',
        'patadj' => 'patadj',
        'insadj' => 'insadj',
        'patpayd' => 'patpayd',
        'insbal' => 'insbal',
        'cashbal' => 'cashbal'
    );

    $child_q = array();
    foreach (tr_pdd_get_batch_types() as $btype) {
        $batch_tbl = tr_pdd_get_table_by_btype($btype);
        $child_q[] = "(
            SELECT
                ba.batch_id,
                ba.assen,
                ba.doc,
                ba.patient_id,
                ba.patient_name,
                ba.requesting_physician AS dname,
                DIS.dis_name AS distributor,
                concat(LR.rep_first,' ',LR.rep_last) as rep,
                0 as ref_idno,
                '' as refname,
                ba.insurance AS pinsname,
                '' as sinsname,
                0 as approved,
                bb.received_amount as collected,
                0 as adjusted,
                0 as patpay,
                0 as patadj,
                0 as insadj,
                '' as patpayd,
                0 as insbal,
                0 as cashbal

                from {$batch_tbl} ba
                left JOIN ci_batch_billings bb ON (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen)
                left join `ci_clinic` as CLI ON CLI.cID = ba.cli_ID
                left JOIN `ci_lab_reps` as LR ON LR.rep_ID = CLI.`cli_rep-ID` 
                left JOIN ci_distributors DIS On DIS.dis_ID = CLI.cli_distributor_ID
                where  ba.batch_id !='' and ba.batch_id!='0' 
                {$where} 
                group by ba.mid 
                )";
    }

    $child_q = implode(' UNION ALL ', $child_q);
    $sql = " select * from ({$child_q}) ba
                order by {$sortby} {$sort}";


    if (isset($_GET['debug'])) {
        echo $sql;
        echo $wpdb->last_error;
    }
    $rows = $wpdb->get_results($sql);

    if (count($rows) == 0) {
        Tr_Session_Class::add_error('Not found!');
        echo $wpdb->last_error;
    }

    if (isset($_REQUEST['debug'])) {
        var_dump($sql);
    }
}
?>

<h2>Audit Report</h2>
<?php Tr_Session_Class::show() ?>

<form method="get">
    <span class="onelinemobile">
        <label>From: </label>
        <input type="search" autocomplete="off" name="date_from" id="from" value="<?php echo $date_from ?>" />
    </span>
    <span class="onelinemobile">
        <label>To: </label>
        <input type="search" autocomplete="off" name="date_to" id="to" value="<?php echo $date_to ?>" />
    </span>

    <input type="submit" name="submit" value="Run Report" class="button bsty6" />
    <input type="submit" name="export" value="Export" class="button" />
    <input type="hidden" name="tr_action" value="ci_export_audit_report" />
    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
</form>


<?php
if (isset($_REQUEST['submit']) && count($rows) > 0) { ?>
    <div>Total: <?php echo count($rows) ?> items</div>

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl">
            <thead>
                <tr>
                    <?php foreach ($headers as $k => $h): ?>
                        <th class="sortable <?php echo $sort ?> <?php if ($sortby == $k) echo 'sorted' ?>">
                            <a href="<?php Tr_Base_Class_V6::link(array('sort' => ($sort == 'desc' ? 'asc' : 'desc'), 'sortby' => $k)) ?>">
                                <span><?php echo $h ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo $row->batch_id ?></td>
                        <td><?php echo $row->assen ?></td>
                        <td><?php echo $row->doc ?></td>
                        <td><?php echo $row->patient_id ?></td>
                        <td><?php echo $row->patient_name ?></td>
                        <td><?php echo $row->dname ?></td>
                        <td><?php echo $row->distributor ?></td>
                        <td><?php echo $row->rep ?></td>
                        <td><?php echo $row->ref_idno ?></td>
                        <td><?php echo $row->refname ?></td>
                        <td><?php echo $row->pinsname ?></td>
                        <td><?php echo $row->sinsname ?></td>
                        <td><?php echo $row->approved ?></td>
                        <td><?php echo $row->collected ?></td>
                        <td><?php echo $row->adjusted ?></td>
                        <td><?php echo $row->patpay ?></td>
                        <td><?php echo $row->patadj ?></td>
                        <td><?php echo $row->insadj ?></td>
                        <td><?php echo $row->patpayd ?></td>
                        <td><?php echo $row->insbal ?></td>
                        <td><?php echo $row->cashbal ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


<?php } ?>

<script>
    jQuery(function($) {
        $("#from").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function(selectedDate) {
                $("#to").datepicker("option", "minDate", selectedDate);
            }
        });
        $("#to").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function(selectedDate) {
                $("#from").datepicker("option", "maxDate", selectedDate);
            }
        });
    })
</script>