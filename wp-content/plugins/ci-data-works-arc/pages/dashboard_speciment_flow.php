<?php
global $wpdb;

$from_date = date_i18n('Y-m-d');

if (isset($_GET['date'])) {
    $from_date = ($_GET['date']);
}

$to_date        = $from_date . ' 23:59:59';
$where          = " and ba.spec_received >= '{$from_date}' and ba.spec_received <= '{$to_date}'";
$where_doc      = " and ba.doc >= '{$from_date}' and ba.doc <= '{$to_date}'";
$where_plate      = " and pl.created >= '{$from_date}' and pl.created <= '{$to_date}'";

$where_resulted = " and re.approve_date >= '{$from_date}' and re.approve_date <= '{$to_date}'";

$speciments_inbound = 0;
$received           = 0;
$in_process         = 0;
$resulted           = 0;
$billed             = 0;
$collected          = 0;

foreach (tr_pdd_get_batch_types() as $btype) {
    $batch_tbl  = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $speciments_inbound += $wpdb->get_var("select count(*) from {$batch_tbl} ba WHERE batch_id = 0 {$where_doc}");

    $received += $wpdb->get_var("select count(*) from {$batch_tbl} ba where 1  {$where}"); //ba.batch_status='received'


    $in_process += $wpdb->get_var("select count(distinct(ba.mid)) from {$batch_tbl} ba 
                            JOIN ci_plates_batches pl ON pl.mid = ba.mid and pl.btype = '{$btype}'
                            WHERE ba.batch_status in ( 'plated') 
                            {$where_plate}");

    $resulted += $wpdb->get_var("select count(*) FROM (select mid from {$batch_tbl} ba 
                                                  JOIN {$report_tbl} re ON ba.assen = re.patient_id
                                                  where 1  {$where_resulted}
                                                  group by ba.mid) as rs ");

    $billed += $wpdb->get_var("select count(*) from {$batch_tbl} ba 
	                left join ci_batch_billings bb ON (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen)
	                WHERE bill_status ='billed' {$where_doc}");
    $collected += $wpdb->get_var("select count(*) from {$batch_tbl} ba where 1  {$where_doc}");
}

?>
<table class="dashboard_tbl">
    <tr>
        <td>Specimens Inbound</td>
        <td><?php echo $speciments_inbound ?></td>
    </tr>
    <tr>
        <td>Received</td>
        <td><?php echo $received ?></td>
    </tr>
    <tr>
        <td>In Process</td>
        <td><?php echo $in_process ?></td>
    </tr>
    <tr>
        <td>Resulted</td>
        <td><?php echo $resulted ?></td>
    </tr>
    <tr>
        <td>Billed</td>
        <td><?php echo $billed ?></td>
    </tr>
    <tr>
        <td>Collected</td>
        <td><?php echo $collected ?></td>
    </tr>
</table>