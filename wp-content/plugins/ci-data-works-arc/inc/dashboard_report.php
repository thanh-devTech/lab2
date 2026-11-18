<?php
global $wpdb,$current_role;
global $ins_mix;
global $lab_id;


$current_offset     = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
$current_time       = time() + $current_offset;
$last_time          = $current_time - 86400;
$current_year       = date('Y',$last_time);

$lab_id = CI_Base_Lab::get_current_lab_id();

$ins_mix = array(
    'payer_commercial' => 'Commercial',
    'payer_medicaid' => 'Medicaid',
    'payer_unknown' => 'Unknown',
    'payer_medicare'      => 'Medicare',
    'payer_workmen' => 'Workmen Comp',
    'payer_other' => 'Other'
);


include(TRCPDD_PATH .'pages/dashboard_specimens.php');

include(TRCPDD_PATH .'pages/dashboard_ar_billing.php');


include(TRCPDD_PATH .'pages/dashboard_ap_to_clms.php');
