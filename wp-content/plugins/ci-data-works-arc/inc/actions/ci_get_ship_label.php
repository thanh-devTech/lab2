<?php
global $wpdb;

$return = array('status'=>'ok');

include_once(TRCPDD_PATH.'inc/ups/ci_shipping_ups.php');
$ups = new CI_Shipping_UPS();

$txn_id = $_REQUEST['txn_id'];

$order = $wpdb->get_row("select * from wp_wp_eStore_customer_tbl od 
                              JOIN ci_customer_orders co ON co.co_txn_id = od.txn_id
                              where od.txn_id = '{$txn_id}' ", ARRAY_A);



$ups->wf_ups_print_label($order);