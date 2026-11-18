<?php
global $wpdb;

$return = array('status'=>'ok');

include_once(TRCPDD_PATH.'inc/ups/ci_shipping_ups.php');
$ups = new CI_Shipping_UPS();

$txn_id = $_REQUEST['txn_id'];

$update = $_POST['ship'];
$update['rates'] = stripslashes($update['rates']);
$wpdb->update('ci_customer_orders',$update,array('co_txn_id'=>$txn_id));

$order = $wpdb->get_row("select * from wp_wp_eStore_customer_tbl od 
                              JOIN ci_customer_orders co ON co.co_txn_id = od.txn_id
                              where od.txn_id = '{$txn_id}' ");





$shipments_details = $ups->wf_ups_shipment_confirm($order);

if(!$shipments_details)
{
    exit;
}

ob_start();
?>
<div class="center" style="margin-top:20px">
    <h3>Shipment ID: <?php echo $shipments_details['ship_id']?></h3>
    <a target="_blank" class="button" href="?tr_action=ci_get_ship_label&txn_id=<?php echo $txn_id?>">Print Label</a>
</div>
<?php
$html = ob_get_clean();

$return['ship_id'] = $shipments_details['ship_id'];
$return['txn_id'] = $txn_id;
$return['html'] = $html;
$rates = @json_decode($order->rates,true);
$return['ship_type'] = $rates[$order->ship_type]['label'].' ( $ '.$order->ship_cost.' )';

$update = array('shipments_details'=>json_encode($shipments_details),'ups'=>$shipments_details['ship_id']);
$wpdb->update('ci_customer_orders',$update,array('co_txn_id'=>$txn_id));

$order->shipments_details  = $update['shipments_details'];

//confirm
$ups->ups_shipment_accept($order);

wp_send_json($return);
