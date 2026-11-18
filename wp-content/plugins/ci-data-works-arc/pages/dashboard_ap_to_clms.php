<?php
global $wpdb;
global $dashboard_ap_to_clms;
global $total_ap_td;
global $total_ap_prev;
global $total_ap_change;
global $blog_id;

ob_start();

$current_offset     = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
$current_time       = time() + $current_offset;

$total_ap_td        = 0;
$total_ap_prev      = 0;
$total_ap_change    = 0;

$aps = array(
    'ap_Service_fees' => 'Service fees',
    'ap_Equipment_Lease' => 'Equipment Lease',
    'ap_Rent' => 'Rent',
    'ap_Accreditation' => 'Accreditation',
    'ap_BOP_Insurance' => 'BOP Insurance',
    'ap_Credentialing' => 'Credentialing',
    'ap_Employee_Leasing' => 'Employee Leasing',
    'ap_Lab_Director' => 'Lab Director',
    'ap_Printing' => 'Printing',
    'ap_Shipping' => 'Shipping'
);

$date_this_month = date('Y-m-01',$current_time);
$date_last_month = date('Y-m-01',strtotime("-1 month") + $current_offset);

if($blog_id>1)
{
    $lab_id = CI_Base_Lab::get_current_lab_id();
    $ap_this_month = $wpdb->get_row("select * from ci_ap_clms where ap_date = '{$date_this_month}' and ap_Lab_ID = {$lab_id}", ARRAY_A);
    $ap_last_month = $wpdb->get_row("select * from ci_ap_clms where ap_date = '{$date_last_month}' and ap_Lab_ID = {$lab_id} ", ARRAY_A);
}else{
    $ap_this_month = $wpdb->get_row("select
                                sum(ap_Service_fees) as ap_Service_fees,
                                sum(ap_Equipment_Lease) as ap_Equipment_Lease,
                                sum(ap_Rent) as ap_Rent,
                                sum(ap_BOP_Insurance) as ap_BOP_Insurance,
                                sum(ap_Credentialing) as ap_Credentialing,
                                sum(ap_Employee_Leasing) as ap_Employee_Leasing,
                                sum(ap_Lab_Director) as ap_Lab_Director,
                                sum(ap_Printing) as ap_Printing,
                                sum(ap_Shipping) as ap_Shipping
                                from ci_ap_clms where ap_date = '{$date_this_month}' group by ap_date ", ARRAY_A);


    $ap_last_month = $wpdb->get_row("select
                                sum(ap_Service_fees) as ap_Service_fees,
                                sum(ap_Equipment_Lease) as ap_Equipment_Lease,
                                sum(ap_Rent) as ap_Rent,
                                sum(ap_BOP_Insurance) as ap_BOP_Insurance,
                                sum(ap_Credentialing) as ap_Credentialing,
                                sum(ap_Employee_Leasing) as ap_Employee_Leasing,
                                sum(ap_Lab_Director) as ap_Lab_Director,
                                sum(ap_Printing) as ap_Printing,
                                sum(ap_Shipping) as ap_Shipping
                                from ci_ap_clms where ap_date = '{$date_last_month}' group by ap_date ", ARRAY_A);
}

?>
<div class="wrap_db">
    <table class="dashboard_tbl">
        <tr>
            <th></th>
            <th><span><?php echo date('M Y',$current_time);?></span></th>
            <th title="<?php echo $date_last_month?>"><span>Previous Month</span></th>
            <th><span>Change</span></th>
        </tr>
        <?php foreach($aps as $key => $txt):
            $td     = isset($ap_this_month[$key]) ? intval($ap_this_month[$key]) : 0;
            $prev   = isset($ap_last_month[$key])? intval($ap_last_month[$key]) : 0;
            $total_ap_td+= $td;
            $total_ap_prev+= $prev;
            ?>
        <tr>
            <td><?php echo $txt?></td>
            <td><?php echo $td?></td>
            <td><?php echo $prev?></td>
            <td><?php echo $td - $prev?></td>
        </tr>
        <?php endforeach?>

        <tr class="totalrow">
            <td></td>
            <td><?php echo $total_ap_td?></td>
            <td><?php echo $total_ap_prev?></td>
            <td><?php $total_ap_change = $total_ap_td - $total_ap_prev;echo $total_ap_change;?></td>
        </tr>
    </table>
</div>

<?php

$dashboard_ap_to_clms = ob_get_clean();