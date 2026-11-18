<?php
global $wpdb;

global $total_specimens_ytd;
global $total_specimens_mtd;
global $total_specimens_td;

global $total_ar_billing_change;
global $total_ar_billing_prev;
global $total_ar_billing_td;

global $total_ap_td;
global $total_ap_prev;
global $total_ap_change;


$current_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
$current_time = time() + $current_offset;
$last_time = $current_time - 86400;
$last_date = date('M d, Y',$last_time);

$today_from = date('Y-m-d',$last_time);
$today_to = date('Y-m-d 23:59:59',$last_time);

$month_from = date('Y-m-1',$last_time);
$month_to = date('Y-m-t 23:59:59',$last_time);

$year_from = date('Y-1-1',$last_time);
$year_to = date('Y-12-31 23:59:59',$last_time);

$where_today = " and ba.spec_received >= '{$today_from}' and ba.spec_received <= '{$today_to}'";
$where_month = " and ba.spec_received >= '{$month_from}' and ba.spec_received <= '{$month_to}'";
$where_year = " and ba.spec_received >= '{$year_from}' and ba.spec_received <= '{$year_to}'";

$billed_today = $wpdb->get_var("select count(*) from ci_batches ba 
            WHERE assen in (select assen from ci_batch_billings where bill_status ='billed') 
            {$where_today}");
$billed_month = $wpdb->get_var("select count(*) from ci_batches ba 
            WHERE assen in (select assen from ci_batch_billings where bill_status ='billed') 
             {$where_month}");
$billed_year = $wpdb->get_var("select count(*) from ci_batches ba 
        WHERE assen in (select assen from ci_batch_billings where bill_status ='billed') 
            {$where_year}");


$collected_today = $wpdb->get_var("select count(*) from ci_batches ba WHERE received_amount > 0 {$where_today}");
$collected_month = $wpdb->get_var("select count(*) from ci_batches ba WHERE received_amount > 0 {$where_month}");
$collected_year = $wpdb->get_var("select count(*) from ci_batches ba WHERE received_amount > 0 {$where_year}");
?>
<div class="wrap_db">
    <table class="dashboard_tbl">
        <tr>
            <th></th>
            <th><span><?php echo date('M Y',$current_time)?></span></th>
            <th><span>Previous Month</span></th>
            <th><span>Change</span></th>
        </tr>
        <tr style="display:none">
            <td class="ap_to_clms">A/P to iLIS</td>
            <td><?php echo $total_ap_td?></td>
            <td><?php echo $total_ap_prev?></td>
            <td><?php echo $total_ap_change?></td>
        </tr>
        <tr>
            <td  class="ar_billing">A/R (Billing)</td>
            <td><?php echo $total_ar_billing_td?></td>
            <td><?php echo $total_ar_billing_prev?></td>
            <td><?php echo $total_ar_billing_change?></td>
        </tr>

        <tr>
            <th></th>
            <th><span><?php echo $last_date?></span></th>
            <th><span>MTD</span></th>
            <th><span>YTD</span></th>
        </tr>
        <tr>
            <td class="specimens">Specimens</td>
            <td><?php echo $total_specimens_td?></td>
            <td><?php echo $total_specimens_mtd?></td>
            <td><?php echo $total_specimens_ytd?></td>
        </tr>
        <tr>
            <td>Billed</td>
            <td><?php echo $billed_today?></td>
            <td><?php echo $billed_month?></td>
            <td><?php echo $billed_year?></td>
        </tr>
        <tr>
            <td>Collected</td>
            <td><?php echo $collected_today?></td>
            <td><?php echo $collected_month?></td>
            <td><?php echo $collected_year?></td>
        </tr>
    </table>
</div>
